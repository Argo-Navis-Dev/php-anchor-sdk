<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep31;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep12\MultipartFormDataset;
use ArgoNavis\PhpAnchorSdk\Sep12\RequestBodyDataParser;
use ArgoNavis\PhpAnchorSdk\callback\ICrossBorderIntegration;
use ArgoNavis\PhpAnchorSdk\callback\IQuotesIntegration;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSep10JwtData;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\exception\Sep31TransactionCallbackNotSupported;
use ArgoNavis\PhpAnchorSdk\exception\Sep31TransactionNotFoundForId;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_pop;
use function count;
use function explode;
use function is_array;
use function preg_match;
use function str_contains;

/**
 * The Sep31Service enables anchors to provide support for payments between two financial accounts that exist outside the Stellar network.
 *
 * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0031.md">SEP-31</a>
 *
 * To create an instance of the service, you have to pass a business logic callback class that implements
 * ICrossBorderPaymentsIntegration to the service constructor. This is needed, so that the service can load
 * supported assets.
 *
 * After initializing the service it can be used within the server implementation by passing all
 * SEP-31 requests to its method handleRequest. It will handle them and return the corresponding response
 * that can be sent back to the client. During the handling it will call methods from the callback implementation
 * (ICrossBorderPaymentsIntegration).
 *
 * See: <a href="https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-31.md">SDK SEP-31 docs</a>
 */
class Sep31Service
{
    /**
     * @var ICrossBorderIntegration $sep31Integration the callback class containing the needed business
     * logic. See ICrossBorderPaymentsIntegration.
     */
    public ICrossBorderIntegration $sep31Integration;

    /**
     * @var IQuotesIntegration|null $quotesIntegration the callback class for quotes if the anchor supports SEP-38
     *  - Quotes.
     */
    public ?IQuotesIntegration $quotesIntegration = null;

    public function __construct(
        ICrossBorderIntegration $sep31Integration,
        ?IQuotesIntegration $quotesIntegration = null,
    ) {
        $this->sep31Integration = $sep31Integration;
        $this->quotesIntegration = $quotesIntegration;
    }

    /**
     * Handles a Sending Anchor request defined by SEP-31. Builds and returns the corresponding response,
     * that can be sent back to the client.
     *
     * @param ServerRequestInterface $request the request from the client as defined in
     * <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>.
     * @param Sep10Jwt $jwtToken the validated jwt token obtained earlier by SEP-10.
     *
     * @return ResponseInterface the response that should be sent back to the client.
     * As defined in <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>
     *
     * @throws InvalidSepRequest
     */
    public function handleRequest(ServerRequestInterface $request, Sep10Jwt $jwtToken): ResponseInterface
    {
        $requestTarget = $request->getRequestTarget();
        if ($request->getMethod() === 'GET') {
            if (str_contains($requestTarget, '/info')) {
                $lang = Sep31RequestParser::getRequestLang($request->getQueryParams());

                return $this->handleGetInfoRequest($jwtToken, $lang);
            } elseif (str_contains($requestTarget, '/transactions')) {
                return $this->handleGetTransactionsRequest(request: $request, jwtToken: $jwtToken);
            }
        } elseif ($request->getMethod() === 'POST') {
            if (str_contains($requestTarget, '/transactions')) {
                return $this->handlePostTransactionsRequest(request: $request, jwtToken: $jwtToken);
            }
        } elseif ($request->getMethod() === 'PUT') {
            if (preg_match('/.*\/transactions\/.*\/callback\/?/', $requestTarget)) {
                return $this->handlePutTransactionsCallbackRequest(request: $request, jwtToken: $jwtToken);
            }
        }

        return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 200);
    }

    /**
     * Handles a GET /info request.
     * Allows an anchor to communicate basic info about what currencies
     * their DIRECT_PAYMENT_SERVER supports receiving from partner anchors.
     *
     * See:
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0031.md#get-info">GET info</a>
     *
     * @param Sep10Jwt $jwtToken obtained via SEP-10.
     * @param string|null $lang the language code for a localized response.
     *
     * @return ResponseInterface response to be sent back to the client
     */
    private function handleGetInfoRequest(Sep10Jwt $jwtToken, ?string $lang = null): ResponseInterface
    {
        try {
            // get account id and memo of the sending anchor from jwt token.
            $accountId = null;
            $accountMemo = null;
            $accountData = $jwtToken->getValidatedAccountData();
            if (isset($accountData['account_id'])) {
                $accountId = $accountData['account_id'];
            }
            if (isset($accountData['account_memo'])) {
                $accountMemo = $accountData['account_memo'];
            }

            if ($accountId === null) {
                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            $supportedAssets = $this->sep31Integration->supportedAssets($accountId, $accountMemo, $lang);

            /**
             * @var array<array-key, mixed> $data
             */
            $data = [];
            foreach ($supportedAssets as $asset) {
                $data[$asset->asset->getCode()] = $asset->toJson();
            }

            return new JsonResponse(['receive' => $data], 200);
        } catch (InvalidSepRequest | InvalidSep10JwtData | AnchorFailure $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Handles a GET /transactions/:id
     * The transactions endpoint enables Sending Clients to fetch information on a specific transaction with the Receiving Anchor.
     *
     * @param ServerRequestInterface $request the request data.
     * @param Sep10Jwt $jwtToken jwt token obtained via SEP-10.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handleGetTransactionsRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            // get account id and memo of the sending anchor from jwt token.
            $accountId = null;
            $accountMemo = null;
            $accountData = $jwtToken->getValidatedAccountData();
            if (isset($accountData['account_id'])) {
                $accountId = $accountData['account_id'];
            }
            if (isset($accountData['account_memo'])) {
                $accountMemo = $accountData['account_memo'];
            }

            if ($accountId === null) {
                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            // extract the transaction id from the request url
            $requestTarget = $request->getRequestTarget();
            $path = explode('/', $requestTarget);
            $transactionId = array_pop($path);
            $transaction = $this->sep31Integration->getTransactionById($transactionId, $accountId, $accountMemo);

            return new JsonResponse(['transaction' => $transaction->toJson()], 200);
        } catch (Sep31TransactionNotFoundForId $qe) {
            return new JsonResponse(['error' => $qe->getMessage()], 404);
        } catch (InvalidSepRequest | InvalidSep10JwtData | AnchorFailure $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Handles a POST /transactions request.
     * This request initiates a payment.
     *
     * @param ServerRequestInterface $request the request data
     * @param Sep10Jwt $jwtToken the jwt token obtained via SEP-10.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handlePostTransactionsRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            // get account id and memo of the sending anchor from jwt token if provided.
            $accountId = null;
            $accountMemo = null;

            $accountData = $jwtToken->getValidatedAccountData();
            if (isset($accountData['account_id'])) {
                $accountId = $accountData['account_id'];
            }
            if (isset($accountData['account_memo'])) {
                $accountMemo = $accountData['account_memo'];
            }

            if ($accountId === null) {
                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            $requestData = $request->getParsedBody();
            // if data is not in getParsedBody(), try to parse with our own parser.
            if (!is_array($requestData) || count($requestData) === 0) {
                $requestData = RequestBodyDataParser::getParsedBodyData(
                    $request,
                    0,
                    0,
                );
                if ($requestData instanceof MultipartFormDataset) {
                    $requestData = $requestData->bodyParams;
                }
            }

            $lang = Sep31RequestParser::getRequestLang($requestData);
            $supportedAssets = $this->sep31Integration->supportedAssets($accountId, $accountMemo, $lang);

            // this also validates data.
            $request = Sep31RequestParser::getPostTransactionRequestFromRequestData(
                accountId: $accountId,
                accountMemo: $accountMemo,
                requestData: $requestData,
                supportedAssets: $supportedAssets,
                quotesIntegration: $this->quotesIntegration,
            );

            $request->clientDomain = $jwtToken->clientDomain;

            $transaction = $this->sep31Integration->postTransaction($request);

            return new JsonResponse($transaction->toJson(), 201);
        } catch (InvalidRequestData | InvalidSepRequest | AnchorFailure $af) {
            return new JsonResponse(['error' => $af->getMessage()], 400);
        }
    }

    /**
     * Handles PUT Transaction Callback.
     *
     * @param ServerRequestInterface $request the request data
     * @param Sep10Jwt $jwtToken the jwt token obtained via SEP-10
     *
     * @return ResponseInterface the response to be sent back to the caller.
     */
    private function handlePutTransactionsCallbackRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            // get account id and memo of the sending anchor from jwt token if provided.
            $accountId = null;
            $accountMemo = null;

            $accountData = $jwtToken->getValidatedAccountData();
            if (isset($accountData['account_id'])) {
                $accountId = $accountData['account_id'];
            }
            if (isset($accountData['account_memo'])) {
                $accountMemo = $accountData['account_memo'];
            }

            if ($accountId === null) {
                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            $requestData = $request->getParsedBody();
            // if data is not in getParsedBody(), try to parse with our own parser.
            if (!is_array($requestData) || count($requestData) === 0) {
                $requestData = RequestBodyDataParser::getParsedBodyData(
                    $request,
                    0,
                    0,
                );
                if ($requestData instanceof MultipartFormDataset) {
                    $requestData = $requestData->bodyParams;
                }
            }

            $requestTarget = $request->getRequestTarget();
            $path = explode('/', $requestTarget);
            array_pop($path);
            $transactionId = array_pop($path);

            if ($transactionId === null) {
                return new JsonResponse(['error' => 'transaction id is mandatory'], 400);
            }

            $this->sep31Integration->getTransactionById($transactionId, $accountId, $accountMemo);

            $putTransactionCallbackRequest = Sep31RequestParser::getPutTransactionCallbackRequestData(
                transactionId: $transactionId,
                requestData: $requestData,
                accountId: $accountId,
                accountMemo: $accountMemo,
            );

            $this->sep31Integration->putTransactionCallback($putTransactionCallbackRequest);

            return new Response\EmptyResponse(204);
        } catch (Sep31TransactionNotFoundForId) {
            return new JsonResponse(['error' => 'transaction not found'], 400);
        } catch (Sep31TransactionCallbackNotSupported) {
            return new Response\EmptyResponse(404);
        } catch (InvalidSepRequest | InvalidRequestData | AnchorFailure $af) {
            return new JsonResponse(['error' => $af->getMessage()], 400);
        }
    }
}
