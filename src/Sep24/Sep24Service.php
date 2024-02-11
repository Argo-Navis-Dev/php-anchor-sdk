<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep24;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep12\MultipartFormDataset;
use ArgoNavis\PhpAnchorSdk\Sep12\RequestBodyDataParser;
use ArgoNavis\PhpAnchorSdk\callback\IInteractiveFlowIntegration;
use ArgoNavis\PhpAnchorSdk\callback\InteractiveDepositRequest;
use ArgoNavis\PhpAnchorSdk\callback\InteractiveTransactionResponse;
use ArgoNavis\PhpAnchorSdk\callback\InteractiveWithdrawRequest;
use ArgoNavis\PhpAnchorSdk\config\ISep24Config;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Soneso\StellarSDK\Crypto\KeyPair;
use Throwable;

use function count;
use function floatval;
use function is_array;
use function is_numeric;
use function is_string;
use function str_contains;
use function trim;

class Sep24Service
{
    public ISep24Config $sep24Config;
    public IInteractiveFlowIntegration $sep24Integration;
    private int $uploadFileMaxSize = 2097152; // 2 MB
    private int $uploadFileMaxCount = 6;

    public function __construct(
        ISep24Config $sep24Config,
        IInteractiveFlowIntegration $sep24Integration,
    ) {
        $this->sep24Config = $sep24Config;
        $this->sep24Integration = $sep24Integration;

        $fMaxSizeMb = $this->sep24Config->getUploadFileMaxSizeMb();
        if ($fMaxSizeMb !== null) {
            $this->uploadFileMaxSize = $fMaxSizeMb * 1048576;
        }
        $fMaxCount = $this->sep24Config->getUploadFileMaxCount();
        if ($fMaxCount !== null) {
            $this->uploadFileMaxCount = $fMaxCount;
        }
    }

    public function handleRequest(ServerRequestInterface $request, ?Sep10Jwt $token = null): ResponseInterface
    {
        $requestTarget = $request->getRequestTarget();
        if ($request->getMethod() === 'GET' && str_contains($requestTarget, '/info')) {
            return new JsonResponse($this->getInfo()->toJson(), 200);
        }
        if (
            $request->getMethod() === 'GET'
            && str_contains($requestTarget, '/fee')
            && !$this->sep24Config->feeEndpointRequiresAuthentication()
        ) {
            return $this->handleGetFeeRequest($request);
        }

        // all other cases require authentication.

        if ($token === null) {
            //403  forbidden
            return new JsonResponse(['type' => 'authentication_required'], 403);
        }

        if ($request->getMethod() === 'GET') {
            if (str_contains($requestTarget, '/fee')) {
                return $this->handleGetFeeRequest($request);
            } elseif (str_contains($requestTarget, '/transactions')) {
                return $this->handleGetTransactionsRequest($request, $token);
            } elseif (str_contains($requestTarget, '/transaction')) {
                return $this->handleGetTransactionRequest($request, $token);
            } else {
                return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 404);
            }
        } elseif ($request->getMethod() === 'POST') {
            if (str_contains($requestTarget, '/transactions/deposit/interactive')) {
                return $this->handlePostDepositRequest($request, $token);
            } elseif (str_contains($requestTarget, '/transactions/withdraw/interactive')) {
                return $this->handlePostWithdrawalRequest($request, $token);
            } else {
                return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 404);
            }
        } else {
            return new JsonResponse(['error' => 'Invalid request. Method not supported.'], 404);
        }
    }

    private function getInfo(): InfoResponse
    {
        $supportedAssets = $this->sep24Integration->supportedAssets();
        /**
         * @var array<string, OperationResponse> $deposit
         */
        $deposit = [];
        /**
         * @var array<string, OperationResponse> $withdraw
         */
        $withdraw = [];
        foreach ($supportedAssets as $supportedAsset) {
            $deposit += [$supportedAsset->asset->getCode() =>
                OperationResponse::fromAssetOperation(
                    $supportedAsset->depositOperation,
                ),
            ];

            $withdraw += [$supportedAsset->asset->getCode() =>
                OperationResponse::fromAssetOperation(
                    $supportedAsset->withdrawOperation,
                ),
            ];
        }

        return new InfoResponse(
            $deposit,
            $withdraw,
            new FeeResponse(
                $this->sep24Config->isFeeEndpointSupported(),
                authenticationRequired: $this->sep24Config->feeEndpointRequiresAuthentication(),
            ),
            new FeaturesFlagResponse(
                $this->sep24Config->isAccountCreationSupported(),
                $this->sep24Config->areClaimableBalancesSupported(),
            ),
        );
    }

    private function handleGetFeeRequest(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->sep24Config->isFeeEndpointSupported()) {
            try {
                return new JsonResponse(['fee' => $this->getFee($request->getQueryParams())], 200);
            } catch (InvalidSepRequest | AnchorFailure $e) {
                return new JsonResponse(['error' => $e->getMessage()], 400);
            }
        } else {
            return new JsonResponse(['error' => 'Fee endpoint is not supported.'], 404);
        }
    }

    private function handlePostWithdrawalRequest(ServerRequestInterface $request, Sep10Jwt $token): ResponseInterface
    {
        try {
            return new JsonResponse($this->withdraw($request, $token)->toJson(), 200);
        } catch (InvalidSepRequest | InvalidRequestData | AnchorFailure $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function handlePostDepositRequest(ServerRequestInterface $request, Sep10Jwt $token): ResponseInterface
    {
        try {
            return new JsonResponse($this->deposit($request, $token)->toJson(), 200);
        } catch (InvalidSepRequest | InvalidRequestData | AnchorFailure $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function handleGetTransactionRequest(ServerRequestInterface $request, Sep10Jwt $token): ResponseInterface
    {
        try {
            $result = null;
            $accountData = $this->accountDataFromToken($token);
            $accountId = null;
            $accountMemo = null;
            if (isset($accountData['account_id'])) {
                $accountId = $accountData['account_id'];
            } else {
                throw new InvalidSepRequest('account id not found in jwt token');
            }
            if (isset($accountData['account_memo'])) {
                $accountMemo = $accountData['account_memo'];
            }
            $queryParameters = $request->getQueryParams();
            $lang = null;
            if (isset($queryParameters['lang'])) {
                if (is_string($queryParameters['lang'])) {
                    $lang = $queryParameters['lang'];
                }
            }
            if (isset($queryParameters['id'])) {
                if (!is_string($queryParameters['id'])) {
                    throw new InvalidSepRequest('id must be a string');
                }
                $id = $queryParameters['id'];
                $result = $this->sep24Integration->findTransactionById(
                    $id,
                    $accountId,
                    $accountMemo,
                    $lang,
                );
            } elseif (isset($queryParameters['stellar_transaction_id'])) {
                if (!is_string($queryParameters['stellar_transaction_id'])) {
                    throw new InvalidSepRequest('stellar_transaction_id must be a string');
                }
                $stellarTransactionId = $queryParameters['stellar_transaction_id'];
                $result = $this->sep24Integration->findTransactionByStellarTransactionId(
                    $stellarTransactionId,
                    $accountId,
                    $accountMemo,
                    $lang,
                );
            } elseif (isset($queryParameters['external_transaction_id'])) {
                if (!is_string($queryParameters['external_transaction_id'])) {
                    throw new InvalidSepRequest('external_transaction_id must be a string');
                }
                $externalTransactionId = $queryParameters['external_transaction_id'];
                $result = $this->sep24Integration->findTransactionByExternalTransactionId(
                    $externalTransactionId,
                    $accountId,
                    $accountMemo,
                    $lang,
                );
            } else {
                throw new InvalidSepRequest(
                    'One of id, stellar_transaction_id or external_transaction_id is required.',
                );
            }
            if ($result !== null) {
                return new JsonResponse(['transaction' => $result->toJson()], 200);
            } else {
                return new JsonResponse(['error' => 'transaction not found'], 404);
            }
        } catch (InvalidSepRequest | AnchorFailure $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function handleGetTransactionsRequest(ServerRequestInterface $request, Sep10Jwt $token): ResponseInterface
    {
        try {
            $accountData = $this->accountDataFromToken($token);
            $accountId = null;
            $accountMemo = null;
            if (isset($accountData['account_id'])) {
                $accountId = $accountData['account_id'];
            } else {
                throw new InvalidSepRequest('account id not found in jwt token');
            }
            if (isset($accountData['account_memo'])) {
                $accountMemo = $accountData['account_memo'];
            }
            $queryParameters = $request->getQueryParams();
            $request = Sep24RequestParser::getTransactionsRequestFromRequestData($queryParameters);
            $result = $this->sep24Integration->getTransactionHistory($request, $accountId, $accountMemo);
            if ($result === null || count($result) === 0) {
                return new JsonResponse([], 200);
            } else {
                $transactionsJson = [];
                foreach ($result as $tx) {
                    $transactionsJson[] = $tx->toJson();
                }

                return new JsonResponse(['transactions' => $transactionsJson], 200);
            }
        } catch (InvalidSepRequest | AnchorFailure $e) {
                return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @return array<string, string> account id and optional memo.
     *
     * @throws InvalidSepRequest if the account was not found or invalid.
     */
    private function accountDataFromToken(Sep10Jwt $token): array
    {
        /**
         * @var array<string, string> $accountData
         */
        $accountData = [];
        if ($token->muxedAccountId !== null) {
            try {
                KeyPair::fromAccountId($token->muxedAccountId);
            } catch (Throwable) {
                throw new InvalidSepRequest('invalid account id in jwt token');
            }
            $accountData['account_id'] = $token->muxedAccountId;
        } elseif ($token->accountId !== null) {
            try {
                KeyPair::fromAccountId($token->accountId);
            } catch (Throwable) {
                throw new InvalidSepRequest('invalid account id in jwt token');
            }
            $accountData['account_id'] = $token->accountId;
            if ($token->accountMemo !== null) {
                $accountData['account_memo'] = $token->accountMemo;
            }
        } else {
            throw new InvalidSepRequest('account id not found in jwt token');
        }

        return $accountData;
    }

    /**
     * @throws InvalidSepRequest
     * @throws InvalidRequestData
     * @throws AnchorFailure
     */
    private function withdraw(ServerRequestInterface $request, Sep10Jwt $token): InteractiveTransactionResponse
    {
        $requestData = $request->getParsedBody();
        /**
         * @var array<array-key, UploadedFileInterface> $uploadedFiles
         */
        $uploadedFiles = $request->getUploadedFiles();

        // if data is not in getParsedBody(), try to parse with our own parser.
        if (!is_array($requestData) || count($requestData) === 0) {
            $requestData = RequestBodyDataParser::getParsedBodyData(
                $request,
                $this->uploadFileMaxSize,
                $this->uploadFileMaxCount,
            );
            if ($requestData instanceof MultipartFormDataset) {
                if (count($uploadedFiles) === 0) {
                    $uploadedFiles = $requestData->uploadedFiles;
                }
                $requestData = $requestData->bodyParams;
            }
        }

        $asset = Sep24RequestParser::getAssetFromRequestData($requestData);
        $destinationAsset = Sep24RequestParser::getDestinationAssetFromRequestData($requestData);
        $amount = Sep24RequestParser::getAmountFromRequestData($requestData);
        $quoteId = Sep24RequestParser::getQuoteIdFromRequestData($requestData);
        $sourceAccount = Sep24RequestParser::getAccountFromRequestData($requestData, $token);
        $memo = Sep24RequestParser::getMemoFromRequestData($requestData);
        $refundMemo = Sep24RequestParser::getRefundMemoFromRequestData($requestData);
        $customerId = Sep24RequestParser::getCustomerIdFromRequestData($requestData);
        $clientSupportsClaimableBalance = Sep24RequestParser::getClaimableBalanceSupportedRequestData($requestData);
        $walletName = Sep24RequestParser::getWalletNameFromRequestData($requestData);
        $walletUrl = Sep24RequestParser::getWalletUrlFromRequestData($requestData);
        $lang = Sep24RequestParser::getLangFromRequestData($requestData);
        $kycFields = Sep24RequestParser::getKycFieldsFromRequestData($requestData);

        // Verify that the asset code is supported, with withdraw enabled.
        $assetInfo = $this->sep24Integration->getAsset($asset->getCode(), $asset->getIssuer());

        if ($assetInfo === null || !$assetInfo->withdrawOperation->enabled) {
            throw new InvalidSepRequest('invalid operation for asset ' . $asset->getCode());
        }

        // Validate min amount
        $minAmount = $assetInfo->withdrawOperation->minAmount;
        if ($minAmount !== null && $amount !== null) {
            if ($amount < $minAmount) {
                throw new InvalidSepRequest("amount is less than asset's minimum limit of: " . $minAmount);
            }
        }

        // Validate max amount
        $maxAmount = $assetInfo->withdrawOperation->maxAmount;
        if ($maxAmount !== null && $amount !== null) {
            if ($amount > $maxAmount) {
                throw new InvalidSepRequest("amount exceeds asset's maximum limit of: " . $maxAmount);
            }
        }

        $withdrawRequest = new InteractiveWithdrawRequest(
            $sourceAccount,
            $clientSupportsClaimableBalance,
            $asset,
            $token,
            destinationAsset: $destinationAsset,
            amount:$amount,
            quoteId:$quoteId,
            memo:$memo,
            walletName: $walletName,
            walletUrl: $walletUrl,
            lang:$lang,
            refundMemo:$refundMemo,
            customerId: $customerId,
            kycFields:$kycFields,
            kycUploadedFiles: $uploadedFiles,
        );

        return $this->sep24Integration->withdraw($withdrawRequest);
    }

    /**
     * @throws InvalidSepRequest
     * @throws InvalidRequestData
     * @throws AnchorFailure
     */
    private function deposit(ServerRequestInterface $request, Sep10Jwt $token): InteractiveTransactionResponse
    {
        $requestData = $request->getParsedBody();
        /**
         * @var array<array-key, UploadedFileInterface> $uploadedFiles
         */
        $uploadedFiles = $request->getUploadedFiles();

        // if data is not in getParsedBody(), try to parse with our own parser.
        if (!is_array($requestData) || count($requestData) === 0) {
            $requestData = RequestBodyDataParser::getParsedBodyData(
                $request,
                $this->uploadFileMaxSize,
                $this->uploadFileMaxCount,
            );
            if ($requestData instanceof MultipartFormDataset) {
                if (count($uploadedFiles) === 0) {
                    $uploadedFiles = $requestData->uploadedFiles;
                }
                $requestData = $requestData->bodyParams;
            }
        }

        $asset = Sep24RequestParser::getAssetFromRequestData($requestData);
        $sourceAsset = Sep24RequestParser::getSourceAssetFromRequestData($requestData);
        $amount = Sep24RequestParser::getAmountFromRequestData($requestData);
        $quoteId = Sep24RequestParser::getQuoteIdFromRequestData($requestData);
        $destinationAccount = Sep24RequestParser::getAccountFromRequestData($requestData, $token);
        $memo = Sep24RequestParser::getMemoFromRequestData($requestData);
        $customerId = Sep24RequestParser::getCustomerIdFromRequestData($requestData);
        $clientSupportsClaimableBalance = Sep24RequestParser::getClaimableBalanceSupportedRequestData($requestData);
        $walletName = Sep24RequestParser::getWalletNameFromRequestData($requestData);
        $walletUrl = Sep24RequestParser::getWalletUrlFromRequestData($requestData);
        $lang = Sep24RequestParser::getLangFromRequestData($requestData);
        $kycFields = Sep24RequestParser::getKycFieldsFromRequestData($requestData);

        // Verify that the asset code is supported, with deposit enabled.
        $assetInfo = $this->sep24Integration->getAsset($asset->getCode(), $asset->getIssuer());

        if ($assetInfo === null || !$assetInfo->depositOperation->enabled) {
            throw new InvalidSepRequest('invalid operation for asset ' . $asset->getCode());
        }

        // Validate min amount
        $minAmount = $assetInfo->depositOperation->minAmount;
        if ($minAmount !== null && $amount !== null) {
            if ($amount < $minAmount) {
                throw new InvalidSepRequest("amount is less than asset's minimum limit of: " . $minAmount);
            }
        }

        // Validate max amount
        $maxAmount = $assetInfo->depositOperation->maxAmount;
        if ($maxAmount !== null && $amount !== null) {
            if ($amount > $maxAmount) {
                throw new InvalidSepRequest("amount exceeds asset's maximum limit of: " . $maxAmount);
            }
        }

        $depositRequest = new InteractiveDepositRequest(
            $destinationAccount,
            $clientSupportsClaimableBalance,
            $asset,
            $token,
            sourceAsset: $sourceAsset,
            amount:$amount,
            quoteId:$quoteId,
            memo:$memo,
            walletName: $walletName,
            walletUrl: $walletUrl,
            lang:$lang,
            customerId: $customerId,
            kycFields:$kycFields,
            kycUploadedFiles: $uploadedFiles,
        );

        return $this->sep24Integration->deposit($depositRequest);
    }

    /**
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     *
     * @throws AnchorFailure
     * @throws InvalidSepRequest
     */
    private function getFee(array $requestData): float
    {
        if (!$this->sep24Config->isFeeEndpointSupported()) {
            throw new InvalidSepRequest('Fee endpoint is not supported.');
        }
        /**
         * @var string $operation
         */
        $operation = null;
        if (isset($requestData['operation'])) {
            if (is_string($requestData['operation'])) {
                $operation = $requestData['operation'];
                if ($operation !== 'deposit' && $operation !== 'withdraw') {
                    throw new InvalidSepRequest('unsupported operation type ' . $operation);
                }
            } else {
                throw new InvalidSepRequest('operation must be a string');
            }
        } else {
            throw new InvalidSepRequest('missing operation');
        }

        $type = null;
        if (isset($requestData['type'])) {
            if (is_string($requestData['type'])) {
                $type = $requestData['type'];
            } else {
                throw new InvalidSepRequest('type must be a string');
            }
        }

        $assetCode = null;
        $assetInfo = null;
        $assetOperation = null;
        if (isset($requestData['asset_code'])) {
            if (is_string($requestData['asset_code'])) {
                $assetCode = trim($requestData['asset_code']);
                // check if asset is supported.
                $assetInfo = $this->sep24Integration->getAsset($assetCode);
                if ($assetInfo === null) {
                    throw new InvalidSepRequest("This anchor doesn't support the given currency code: " . $assetCode);
                }
                if ($operation === 'deposit') {
                    $assetOperation = $assetInfo->depositOperation;
                } else {
                    $assetOperation = $assetInfo->withdrawOperation;
                }
                if (!$assetOperation->enabled) {
                    throw new InvalidSepRequest($operation .
                        ' operation not supported for the currency code: ' .
                        $assetCode);
                }
            } else {
                throw new InvalidSepRequest('asset code must be a string');
            }
        } else {
            throw new InvalidSepRequest('missing asset code');
        }

        $amount = null;
        if (isset($requestData['amount'])) {
            if (is_numeric($requestData['amount'])) {
                $amount = floatval($requestData['amount']);
                if ($amount <= 0.0) {
                    throw new InvalidSepRequest('negative and zero amounts are not supported.');
                }
            } else {
                throw new InvalidSepRequest('amount must be a float');
            }
        } else {
            throw new InvalidSepRequest('missing amount');
        }

        // check if amount is in range
        if ($assetOperation->minAmount !== null && $amount < $assetOperation->minAmount) {
            throw new InvalidSepRequest("amount is less than asset's minimum limit of: " . $assetOperation->minAmount);
        }

        if ($assetOperation->maxAmount !== null && $amount > $assetOperation->maxAmount) {
            throw new InvalidSepRequest("amount exceeds asset's maximum limit of: " . $assetOperation->maxAmount);
        }

        // check if the fee can easily be calculated by the given asset info
        // SEP-24 says: If fee_fixed or fee_percent are provided,
        // the total fee is calculated as (amount * fee_percent) + fee_fixed = fee_total.
        // If the fee structure doesn't fit this model, omit them and provide the /fee endpoint instead.
        if (
            $this->sep24Config->shouldSdkCalculateObviousFee() &&
            ($assetOperation->feeFixed !== null || $assetOperation->feePercent !== null)
        ) {
            $fee = $assetOperation->feeFixed ?? 0.0;
            if ($assetOperation->feePercent !== null) {
                $fee += $amount * $assetOperation->feePercent;
            }
            if ($assetOperation->feeMinimum !== null && $fee < $assetOperation->feeMinimum) {
                $fee = $assetOperation->feeMinimum;
            }

            return $fee;
        } else {
            // complex fee calculation
            return $this->sep24Integration->getFee($operation, $assetCode, $amount, type: $type);
        }
    }
}
