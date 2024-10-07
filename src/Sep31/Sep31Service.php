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
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use ArgoNavis\PhpAnchorSdk\util\MemoHelper;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use function array_pop;
use function count;
use function explode;
use function is_array;
use function json_encode;
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

    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private LoggerInterface | NullLogger $logger;

    public function __construct(
        ICrossBorderIntegration $sep31Integration,
        ?IQuotesIntegration $quotesIntegration = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->sep31Integration = $sep31Integration;
        $this->quotesIntegration = $quotesIntegration;

        $this->logger = $logger ?? new NullLogger();
        Sep10Jwt::setLogger($this->logger);
        Sep31RequestParser::setLogger($this->logger);
        MemoHelper::setLogger($this->logger);
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
     */
    public function handleRequest(ServerRequestInterface $request, Sep10Jwt $jwtToken): ResponseInterface
    {
        $requestTarget = $request->getRequestTarget();
        $this->logger->info(
            'Handling incoming request.',
            ['context' => 'sep31', 'method' => $request->getMethod(), 'request_target' => $requestTarget],
        );

        if ($request->getMethod() === 'GET') {
            if (str_contains($requestTarget, '/info')) {
                $this->logger->info(
                    'Executing get anchor info request.',
                    ['context' => 'sep31', 'operation' => 'anchor_info'],
                );

                return $this->handleGetInfoRequest(request: $request, jwtToken: $jwtToken);
            } elseif (str_contains($requestTarget, '/transactions')) {
                $this->logger->info(
                    'Executing get anchor transaction request.',
                    ['context' => 'sep31', 'operation' => 'transaction'],
                );

                return $this->handleGetTransactionsRequest(request: $request, jwtToken: $jwtToken);
            }
        } elseif ($request->getMethod() === 'POST') {
            if (str_contains($requestTarget, '/transactions')) {
                $this->logger->info(
                    'Executing new anchor transaction (payment) request.',
                    ['context' => 'sep31', 'operation' => 'new_transaction'],
                );

                return $this->handlePostTransactionsRequest(request: $request, jwtToken: $jwtToken);
            }
        } elseif ($request->getMethod() === 'PUT') {
            if (preg_match('/.*\/transactions\/.*\/callback\/?/', $requestTarget)) {
                $this->logger->info(
                    'Executing put transaction (payment) callback request.',
                    ['context' => 'sep31', 'operation' => 'put_transaction_callback'],
                );

                return $this->handlePutTransactionsCallbackRequest(request: $request, jwtToken: $jwtToken);
            }
        }
        $this->logger->error(
            'Invalid request, unknown endpoint.',
            ['context' => 'sep31', 'http_status_code' => 200],
        );

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
     * @param ServerRequestInterface $request the request data.
     * @param Sep10Jwt $jwtToken obtained via SEP-10.
     *
     * @return ResponseInterface response to be sent back to the client
     */
    private function handleGetInfoRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            $queryParameters = $request->getQueryParams();
            $this->logger->debug(
                'Query parameters before processing.',
                ['context' => 'sep31', 'operation' => 'anchor_info',
                    'query_parameters' => json_encode($queryParameters),
                ],
            );

            $lang = Sep31RequestParser::getRequestLang($queryParameters);
            $this->logger->debug(
                'Query parameters after processing.',
                ['context' => 'sep31', 'operation' => 'anchor_info', 'lang' => $lang],
            );

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
                $this->logger->error(
                    'Invalid jwt token.',
                    ['context' => 'sep31', 'operation' => 'anchor_info', 'http_status_code' => 403],
                );

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
            $this->logger->debug(
                'Anchor info built successfully.',
                ['context' => 'sep31', 'operation' => 'anchor_info', 'content' => json_encode($data)],
            );

            return new JsonResponse(['receive' => $data], 200);
        } catch (InvalidSepRequest | InvalidSep10JwtData | AnchorFailure $e) {
            $this->logger->debug(
                'Failed to build anchor info.',
                ['context' => 'sep31', 'operation' => 'anchor_info',
                    'error' => $e->getMessage(),
                    'exception' => $e,
                    'http_status_code' => 400,
                ],
            );

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
                $this->logger->error(
                    'Invalid jwt token.',
                    ['context' => 'sep31', 'operation' => 'transaction', 'http_status_code' => 403],
                );

                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            // extract the transaction id from the request url
            $requestTarget = $request->getRequestTarget();
            $path = explode('/', $requestTarget);
            $transactionId = array_pop($path);

            $this->logger->debug(
                'Retrieving transaction by id.',
                ['context' => 'sep31', 'operation' => 'transaction', 'id' => $transactionId],
            );

            $transaction = $this->sep31Integration->getTransactionById($transactionId, $accountId, $accountMemo);
            $this->logger->debug(
                'The transaction found.',
                ['context' => 'sep31', 'operation' => 'transaction', 'content' => $transaction],
            );

            return new JsonResponse(['transaction' => $transaction->toJson()], 200);
        } catch (Sep31TransactionNotFoundForId $qe) {
            $this->logger->error(
                'Failed to retrieve transaction by id.',
                ['context' => 'sep31', 'operation' => 'transaction',
                    'error' => $qe->getMessage(), 'exception' => $qe, 'http_status_code' => 404,
                ],
            );

            return new JsonResponse(['error' => $qe->getMessage()], 404);
        } catch (InvalidSepRequest | InvalidSep10JwtData | AnchorFailure $e) {
            $this->logger->error(
                'Failed to retrieve transaction by id.',
                ['context' => 'sep31', 'operation' => 'transaction',
                    'error' => $e->getMessage(), 'exception' => $e, 'http_status_code' => 400,
                ],
            );

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
                $this->logger->error(
                    'Invalid jwt token.',
                    ['context' => 'sep31', 'operation' => 'new_transaction', 'http_status_code' => 403],
                );

                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            $requestData = $request->getParsedBody();
            // if data is not in getParsedBody(), try to parse with our own parser.
            if (!is_array($requestData) || count($requestData) === 0) {
                $this->logger->debug(
                    'The request body data is empty, try to parse with the SDK parser.',
                    ['context' => 'sep31', 'operation' => 'new_transaction'],
                );

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
            $this->logger->debug(
                'The request data.',
                ['context' => 'sep31', 'operation' => 'new_transaction', 'content' => json_encode($requestData)],
            );

            $supportedAssets = $this->sep31Integration->supportedAssets($accountId, $accountMemo, $lang);

            // this also validates data.
            $request = Sep31RequestParser::getPostTransactionRequestFromRequestData(
                accountId: $accountId,
                accountMemo: $accountMemo,
                requestData: $requestData,
                supportedAssets: $supportedAssets,
                quotesIntegration: $this->quotesIntegration,
            );
            $this->logger->debug(
                'The transaction request from request data.',
                ['context' => 'sep31', 'operation' => 'new_transaction', 'content' => json_encode($request)],
            );

            $request->clientDomain = $jwtToken->clientDomain;

            $transaction = $this->sep31Integration->postTransaction($request);

            $this->logger->debug(
                'New transaction (payment) has been created successfully .',
                ['context' => 'sep31', 'operation' => 'new_transaction', 'content' => json_encode($transaction)],
            );

            return new JsonResponse($transaction->toJson(), 201);
        } catch (InvalidRequestData | InvalidSepRequest | AnchorFailure $af) {
            $this->logger->error(
                'Failed to make a new transaction (payment).',
                ['context' => 'sep31', 'operation' => 'new_transaction',
                    'error' => $af->getMessage(), 'exception' => $af, 'http_status_code' => 400,
                ],
            );

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
                $this->logger->error(
                    'Invalid jwt token.',
                    ['context' => 'sep31', 'operation' => 'put_transaction_callback', 'http_status_code' => 403],
                );

                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            $requestData = $request->getParsedBody();
            // if data is not in getParsedBody(), try to parse with our own parser.
            if (!is_array($requestData) || count($requestData) === 0) {
                $this->logger->debug(
                    'The request body data is empty, try to parse with the SDK parser.',
                    ['context' => 'sep31', 'operation' => 'put_transaction_callback'],
                );

                $requestData = RequestBodyDataParser::getParsedBodyData(
                    $request,
                    0,
                    0,
                );
                if ($requestData instanceof MultipartFormDataset) {
                    $requestData = $requestData->bodyParams;
                }
            }

            $this->logger->debug(
                'The request data.',
                ['context' => 'sep31', 'operation' => 'put_transaction_callback',
                    'content' => json_encode($requestData),
                ],
            );

            $requestTarget = $request->getRequestTarget();
            $path = explode('/', $requestTarget);
            array_pop($path);
            $transactionId = array_pop($path);

            $this->logger->debug(
                'Registering callback by transaction id.',
                ['context' => 'sep31', 'operation' => 'put_transaction_callback', 'id' => $transactionId],
            );

            if ($transactionId === null) {
                $this->logger->error(
                    'Transaction id is mandatory.',
                    ['context' => 'sep31', 'operation' => 'put_transaction_callback', 'http_status_code' => 400],
                );

                return new JsonResponse(['error' => 'transaction id is mandatory'], 400);
            }

            $this->sep31Integration->getTransactionById($transactionId, $accountId, $accountMemo);

            $putTransactionCallbackRequest = Sep31RequestParser::getPutTransactionCallbackRequestData(
                transactionId: $transactionId,
                requestData: $requestData,
                accountId: $accountId,
                accountMemo: $accountMemo,
            );
            $this->logger->debug(
                'The put transaction request from request data.',
                ['context' => 'sep31', 'operation' => 'put_transaction_callback',
                    'content' => json_encode($putTransactionCallbackRequest),
                ],
            );

            $this->sep31Integration->putTransactionCallback($putTransactionCallbackRequest);
            $this->logger->debug(
                'Transaction (payment) callback has been saved successfully.',
                ['context' => 'sep31', 'operation' => 'put_transaction_callback'],
            );

            return new Response\EmptyResponse(204);
        } catch (Sep31TransactionNotFoundForId $txNotFound) {
            $this->logger->error(
                'Failed to save transaction (payment) callback, transaction not found.',
                ['context' => 'sep31', 'operation' => 'put_transaction_callback',
                    'error' => $txNotFound->getMessage(), 'exception' => $txNotFound, 'http_status_code' => 400,
                ],
            );

            return new JsonResponse(['error' => 'transaction not found'], 400);
        } catch (Sep31TransactionCallbackNotSupported $callbackNs) {
            $this->logger->error(
                'Failed to save transaction (payment) callback, transaction callback not supported.',
                ['context' => 'sep31', 'operation' => 'put_transaction_callback',
                    'error' => $callbackNs->getMessage(), 'exception' => $callbackNs, 'http_status_code' => 404,
                ],
            );

            return new Response\EmptyResponse(404);
        } catch (InvalidSepRequest | InvalidRequestData | AnchorFailure $af) {
            $this->logger->error(
                'Failed to save transaction (payment) callback.',
                ['context' => 'sep31', 'operation' => 'put_transaction_callback',
                    'error' => $af->getMessage(), 'exception' => $af, 'http_status_code' => 400,
                ],
            );

            return new JsonResponse(['error' => $af->getMessage()], 400);
        }
    }
}
