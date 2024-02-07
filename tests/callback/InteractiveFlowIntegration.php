<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\callback\IInteractiveFlowIntegration;
use ArgoNavis\PhpAnchorSdk\callback\InteractiveDepositRequest;
use ArgoNavis\PhpAnchorSdk\callback\InteractiveTransactionResponse;
use ArgoNavis\PhpAnchorSdk\callback\InteractiveWithdrawRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep24DepositTransactionResponse;
use ArgoNavis\PhpAnchorSdk\callback\Sep24TransactionHistoryRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep24TransactionResponse;
use ArgoNavis\PhpAnchorSdk\callback\Sep24WithdrawTransactionResponse;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\shared\DepositOperation;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep24AssetInfo;
use ArgoNavis\PhpAnchorSdk\shared\Sep24TransactionStatus;
use ArgoNavis\PhpAnchorSdk\shared\WithdrawOperation;
use DateTime;

use function strval;
use function uniqid;

class InteractiveFlowIntegration implements IInteractiveFlowIntegration
{
    /**
     * @var array<Sep24AssetInfo> $supportedAssets the list of supported assets.
     */
    private array $supportedAssets;
    /**
     * @var array<Sep24TestTransaction> $transactions cache of transactions.
     */
    private array $transactions = [];

    public function __construct()
    {
        $this->supportedAssets = self::composeSupportedAssets();
    }

    /**
     * @inheritDoc
     */
    public function supportedAssets(): array
    {
        return $this->supportedAssets;
    }

    public function getAsset(string $code, ?string $issuer = null): ?Sep24AssetInfo
    {
        foreach ($this->supportedAssets as $assetInfo) {
            $asset = $assetInfo->asset;
            if ($asset->getCode() === $code) {
                if ($issuer === null) {
                    return $assetInfo;
                } elseif ($issuer === $asset->getIssuer()) {
                    return $assetInfo;
                }
            }
        }

        return null;
    }

    public function getFee(string $operation, string $assetCode, float $amount, ?string $type = null): float
    {
        if ($operation === 'withdraw' && $assetCode === 'ETH') {
            return 0.13;
        } elseif ($operation === 'withdraw' && $assetCode === IdentificationFormatAsset::NATIVE_ASSET_CODE) {
            return 1.0;
        } else {
            throw new AnchorFailure('fee can not be calculated');
        }
    }

    public function withdraw(InteractiveWithdrawRequest $request): InteractiveTransactionResponse
    {
        $stellarTxId = $request->walletName; // hack for test
        $externalTxId = $request->walletUrl; // hack for test

        $tx = self::transactionFrom($request, $stellarTxId, $externalTxId);
        $this->transactions[] = $tx;

        return new InteractiveTransactionResponse(
            type: 'interactive_customer_info_needed',
            url: 'https://test.com/interpop/' . $tx->data->id,
            id: $tx->data->id,
        );
    }

    public function deposit(InteractiveDepositRequest $request): InteractiveTransactionResponse
    {
        $stellarTxId = $request->walletName; // hack for test
        $externalTxId = $request->walletUrl; // hack for test

        $tx = self::transactionFrom($request, $stellarTxId, $externalTxId);
        $this->transactions[] = $tx;

        return new InteractiveTransactionResponse(
            type: 'interactive_customer_info_needed',
            url: 'https://test.com/interpop/' . $tx->data->id,
            id: $tx->data->id,
        );
    }

    public function findTransactionById(
        string $id,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep24TransactionResponse {
        foreach ($this->transactions as $tx) {
            if ($tx->data->id === $id && $tx->account === $accountId) {
                if ($accountMemo === null || $accountMemo === $tx->accountMemo) {
                    return $tx->data;
                }
            }
        }

        return null;
    }

    public function findTransactionByStellarTransactionId(
        string $stellarTransactionId,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep24TransactionResponse {
        foreach ($this->transactions as $tx) {
            if ($tx->data->stellarTransactionId === $stellarTransactionId && $tx->account === $accountId) {
                if ($accountMemo === null || $accountMemo === $tx->accountMemo) {
                    return $tx->data;
                }
            }
        }

        return null;
    }

    public function findTransactionByExternalTransactionId(
        string $externalTransactionId,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep24TransactionResponse {
        foreach ($this->transactions as $tx) {
            if ($tx->data->externalTransactionId === $externalTransactionId && $tx->account === $accountId) {
                if ($accountMemo === null || $accountMemo === $tx->accountMemo) {
                    return $tx->data;
                }
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getTransactionHistory(
        Sep24TransactionHistoryRequest $request,
        string $accountId,
        ?string $accountMemo = null,
    ): ?array {
        /**
         * @var array<Sep24TransactionResponse> $result
         */
        $result = [];
        foreach ($this->transactions as $tx) {
            if ($tx->account === $accountId && $request->assetCode === $tx->asset->getCode()) {
                if ($accountMemo === null || $accountMemo === $tx->accountMemo) {
                    $result[] = $tx->data;
                }
            }
        }

        return $result;
    }

    /**
     * @return array<Sep24AssetInfo>
     */
    private static function composeSupportedAssets(): array
    {
        $usd = new IdentificationFormatAsset(schema: IdentificationFormatAsset::ASSET_SCHEMA_ISO4217, code:'USD');
        $eth = new IdentificationFormatAsset(
            schema: IdentificationFormatAsset::ASSET_SCHEMA_STELLAR,
            code: 'ETH',
            issuer: 'GDLW7I64UY2HG4PWVJB2KYLG5HWPRCIDD3WUVRFJMJASX4CB7HJVDOYP',
        );
        $native = new IdentificationFormatAsset(
            schema: IdentificationFormatAsset::ASSET_SCHEMA_STELLAR,
            code: IdentificationFormatAsset::NATIVE_ASSET_CODE,
        );

        $usdDepositOp = new DepositOperation(
            enabled: true,
            minAmount: 0.1,
            maxAmount: 1000,
            feeFixed: 5,
            feePercent: 1,
        );
        $usdWithdrawOp = new WithdrawOperation(
            enabled: true,
            minAmount: 0.1,
            maxAmount: 1000,
            feePercent: 0.5,
            feeMinimum: 5,
        );
        $usdInfo = new Sep24AssetInfo(asset: $usd, depositOperation: $usdDepositOp, withdrawOperation: $usdWithdrawOp);

        $ethDepositOp = new DepositOperation(enabled: true, feeFixed: 0.002, feePercent: 0);
        $ethWithdrawOp = new WithdrawOperation(enabled: false);
        $ethInfo = new Sep24AssetInfo(asset: $eth, depositOperation: $ethDepositOp, withdrawOperation: $ethWithdrawOp);

        $nativeDepositOp = new DepositOperation(enabled: true, feeFixed: 0.00001, feePercent: 0);
        $nativeWithdrawOp = new WithdrawOperation(enabled: true);
        $nativeInfo = new Sep24AssetInfo(
            asset: $native,
            depositOperation: $nativeDepositOp,
            withdrawOperation: $nativeWithdrawOp,
        );

        return [$usdInfo, $ethInfo, $nativeInfo];
    }

    /**
     * @throws AnchorFailure
     */
    public static function transactionFrom(
        InteractiveWithdrawRequest | InteractiveDepositRequest $request,
        ?string $stellarTxId = null,
        ?string $externalTxId = null,
    ): Sep24TestTransaction {
        $status = Sep24TransactionStatus::PENDING_USER_TRANSFER_START;
        $id = uniqid();
        $account = $request->jwtToken->accountId;
        $accountMemo = null;
        if ($account === null) {
            $account = $request->jwtToken->muxedAccountId;
        } else {
            $accountMemo = $request->jwtToken->accountMemo;
        }
        if ($account === null) {
            throw new AnchorFailure('could not extract account from jwt token');
        }

        if ($request instanceof InteractiveWithdrawRequest) {
            $data = new Sep24WithdrawTransactionResponse(
                id: $id,
                from: $request->account,
                to: 'GB29 NWBK 6016 1331 9268 19',
                withdrawAnchorAccount: 'GAOSWQSWDSHIYWL2P3D5G7UN23DYNWECY342RNOSO3S4AWUDBLDH2JOS',
                status: $status,
                moreInfoUrl: 'https://test.com/more/' . $id,
                amountIn: $request->amount !== null ? strval($request->amount) : '100.0',
                amountOut: '110.0',
                amountFee: '10.0',
                startedAt: new DateTime('now'),
                stellarTransactionId: $stellarTxId,
                withdrawMemo: '771626434',
                withdrawMemoType: 'id',
                statusEta: 2500,
                kycVerified: true,
                amountInAsset: $request->asset,
                amountOutAsset: $request->destinationAsset,
                amountFeeAsset: $request->asset,
                externalTransactionId: $externalTxId,
                message: 'hi',
                refunded: false,
            );
        } else {
            $data = new Sep24DepositTransactionResponse(
                id: $id,
                from: 'GB29 NWBK 6016 1331 9268 19',
                to: $request->account,
                status: $status,
                moreInfoUrl: 'https://test.com/more/' . $id,
                amountIn: $request->amount !== null ? strval($request->amount) : '20.0',
                amountOut: '200.0',
                amountFee: '2.0',
                startedAt: new DateTime('now'),
                stellarTransactionId: $stellarTxId,
                depositMemo: '88282772',
                depositMemoType: 'id',
                statusEta: 3200,
                kycVerified: true,
                amountInAsset: $request->sourceAsset,
                amountOutAsset: $request->asset,
                amountFeeAsset: $request->asset,
                externalTransactionId: $externalTxId,
                message: 'hi',
                refunded: false,
            );
        }

        return new Sep24TestTransaction($request->asset, $data, $account, $accountMemo);
    }
}