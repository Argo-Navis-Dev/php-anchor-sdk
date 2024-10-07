<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep38;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep12\MultipartFormDataset;
use ArgoNavis\PhpAnchorSdk\Sep12\RequestBodyDataParser;
use ArgoNavis\PhpAnchorSdk\callback\IQuotesIntegration;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\exception\QuoteNotFoundForId;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_pop;
use function count;
use function explode;
use function is_array;
use function json_encode;
use function str_contains;
use function trim;

/**
 * The Sep38Service enables anchors to provide quotes that can be referenced within the context
 * of existing Stellar Ecosystem Proposals. See:
 *
 * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md">SEP-38</a>
 *
 * To create an instance of the service, you have to pass a business logic callback class that implements
 * IQuotesIntegration to the service constructor. This is needed, so that the service can load
 * supported assets, prices, quotes.
 *
 * After initializing the service it can be used within the server implementation by passing all
 * SEP-38 requests to its method handleRequest. It will handle them and return the corresponding response
 * that can be sent back to the client. During the handling it will call methods from the callback implementation
 * (IQuotesIntegration).
 *
 * See: <a href="https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-38.md">SDK SEP-38 docs</a>
 */
class Sep38Service
{
    public IQuotesIntegration $sep38Integration;
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private LoggerInterface | NullLogger $logger;

    /**
     * @param IQuotesIntegration $sep38Integration the callback class providing the needed business
     *  logic. See IQuotesIntegration description.
     */
    public function __construct(IQuotesIntegration $sep38Integration, ?LoggerInterface $logger = null)
    {
        $this->sep38Integration = $sep38Integration;
        $this->logger = $logger ?? new NullLogger();
        Sep10Jwt::setLogger($this->logger);
        Sep38RequestParser::setLogger($this->logger);
    }

    /**
     * Handles a forwarded client request specified by SEP-38. Builds and returns the corresponding response,
     * that can be sent back to the client.
     *
     * @param ServerRequestInterface $request the request from the client as defined in
     * <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>.
     * @param Sep10Jwt|null $jwtToken the validated jwt token obtained earlier by SEP-10 if any.
     *
     * @return ResponseInterface the response that should be sent back to the client.
     * As defined in <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>
     */
    public function handleRequest(ServerRequestInterface $request, ?Sep10Jwt $jwtToken = null): ResponseInterface
    {
        $requestTarget = $request->getRequestTarget();
        $this->logger->info(
            'Handling incoming request.',
            ['context' => 'sep38', 'method' => $request->getMethod(), 'request_target' => $requestTarget],
        );

        if ($request->getMethod() === 'GET') {
            if (str_contains($requestTarget, '/info')) {
                $this->logger->info(
                    'Executing get quote info request.',
                    ['context' => 'sep38', 'operation' => 'info'],
                );

                return $this->handleGetInfoRequest(jwtToken: $jwtToken);
            } elseif (str_contains($requestTarget, '/prices')) {
                $this->logger->info(
                    'Retrieving the prices.',
                    ['context' => 'sep38', 'operation' => 'prices'],
                );

                return $this->handleGetPricesRequest(request: $request, jwtToken: $jwtToken);
            } elseif (str_contains($requestTarget, '/price')) {
                $this->logger->info(
                    'Retrieving a price.',
                    ['context' => 'sep38', 'operation' => 'price'],
                );

                return $this->handleGetPriceRequest(request: $request, jwtToken: $jwtToken);
            } elseif (str_contains($requestTarget, '/quote')) {
                if ($jwtToken === null) {
                    $this->logger->error(
                        'SEP-10 authentication required.',
                        ['context' => 'sep38', 'operation' => 'quote', 'http_status_code' => 403],
                    );

                    //403  forbidden
                    return new JsonResponse(['error' => 'SEP-10 authentication required'], 403);
                }
                $this->logger->info(
                    'Retrieving a quote.',
                    ['context' => 'sep38', 'operation' => 'quote'],
                );

                return $this->handleGetQuoteRequest(request:$request, jwtToken: $jwtToken);
            }
        } elseif ($request->getMethod() === 'POST') {
            if (str_contains($requestTarget, '/quote')) {
                if ($jwtToken === null) {
                    $this->logger->error(
                        'SEP-10 authentication required.',
                        ['context' => 'sep38', 'operation' => 'new_quote', 'http_status_code' => 403],
                    );

                    //403  forbidden
                    return new JsonResponse(['error' => 'SEP-10 authentication required'], 403);
                }
                $this->logger->info(
                    'Making a firm quote.',
                    ['context' => 'sep38', 'operation' => 'new_quote'],
                );

                return $this->handlePostQuoteRequest(request:$request, jwtToken: $jwtToken);
            }
        } else {
            $this->logger->error(
                'The HTTP method not supported.',
                ['context' => 'sep38', 'http_method' => $request->getMethod(), 'http_status_code' => 404],
            );

            return new JsonResponse(['error' => 'Invalid request. Method not supported.'], 404);
        }
        $this->logger->error(
            'Invalid request, unknown endpoint.',
            ['context' => 'sep38', 'http_status_code' => 404,
                'request_target' => $requestTarget, 'method' => $request->getMethod(),
            ],
        );

        return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 404);
    }

    /**
     * Handles a GET /info request.
     * This endpoint describes the supported Stellar assets and off-chain assets available for trading.
     *
     * See:
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md#get-info">GET info</a>
     *
     * @param Sep10Jwt|null $jwtToken the validated jwt token obtained earlier by SEP-10 if any.
     *
     * @return ResponseInterface response to be sent back to the client
     */
    private function handleGetInfoRequest(?Sep10Jwt $jwtToken = null): ResponseInterface
    {
        try {
            // get account id and memo of the user from jwt token if provided.
            $accountId = null;
            $accountMemo = null;

            if ($jwtToken !== null) {
                $accountData = $jwtToken->getValidatedAccountData();
                if (isset($accountData['account_id'])) {
                    $accountId = $accountData['account_id'];
                }
                if (isset($accountData['account_memo'])) {
                    $accountMemo = $accountData['account_memo'];
                }
            }

            // fetch the supported assets from the server integration.
            $supportedAssets = $this->sep38Integration->supportedAssets(
                accountId:$accountId,
                accountMemo:$accountMemo,
            );

            // format response.
            $formattedAssets = [];
            foreach ($supportedAssets as $asset) {
                $formattedAssets[] = $asset->toJson();
            }
            $this->logger->debug(
                'Price and quote info built successfully.',
                ['context' => 'sep31', 'operation' => 'anchor_info', 'content' => $formattedAssets],
            );

            return new JsonResponse(['assets' => $formattedAssets], 200);
        } catch (Throwable $t) {
            $this->logger->debug(
                'Failed to build the price and quote info.',
                ['context' => 'sep31', 'operation' => 'anchor_info',
                    'error' => $t->getMessage(), 'exception' => $t, 'http_status_code' => 400,
                ],
            );

            return new JsonResponse(['error' => $t->getMessage()], 400);
        }
    }

    /**
     * Handles a GET /prices request.
     *
     * This endpoint can be used to fetch the indicative prices of available off-chain assets in exchange
     * for a Stellar asset and vice versa.
     * These prices are indicative. The actual price will be calculated at conversion time once the Anchor
     * receives the funds from a User.
     * The prices returned include any margin that the provider may keep as a service fee,
     * and this margin may vary depending on the directional flow of funds, amount, delivery method,
     * country code, or other factors.
     *
     * See:
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md#get-prices">GET prices</a>
     *
     * @param ServerRequestInterface $request the request data from the client.
     * @param Sep10Jwt|null $jwtToken the jwt token obtained from SEP-10 if any.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handleGetPricesRequest(
        ServerRequestInterface $request,
        ?Sep10Jwt $jwtToken = null,
    ): ResponseInterface {
        try {
            // get account id and memo of the user from jwt token if provided.
            $accountId = null;
            $accountMemo = null;

            if ($jwtToken !== null) {
                $accountData = $jwtToken->getValidatedAccountData();
                if (isset($accountData['account_id'])) {
                    $accountId = $accountData['account_id'];
                }
                if (isset($accountData['account_memo'])) {
                    $accountMemo = $accountData['account_memo'];
                }
            }

            // read the query parameters from the request
            $queryParameters = $request->getQueryParams();
            $this->logger->debug(
                'Query parameters before processing.',
                ['context' => 'sep38', 'operation' => 'prices',
                    'query_parameters' => json_encode($queryParameters),
                ],
            );

            // fetch the supported assets from the server integration.
            $supportedAssets = $this->sep38Integration->supportedAssets(
                accountId: $accountId,
                accountMemo:$accountMemo,
            );

            // validate the request data and build a server integration request object from it
            $request = Sep38RequestParser::getPricesRequestFromRequestData(
                requestData: $queryParameters,
                supportedAssets: $supportedAssets,
            );
            $this->logger->debug(
                'Parameters after processing.',
                ['context' => 'sep38', 'operation' => 'prices', 'parameters' => json_encode($request)],
            );

            // attach user data extracted from jwt token if any
            $request->accountId = $accountId;
            $request->accountMemo = $accountMemo;

            // request the prices from the server integration.
            $buyAssets = $this->sep38Integration->getPrices(request: $request);

            // format response
            $result = [];
            foreach ($buyAssets as $asset) {
                $result[] = $asset->toJson();
            }
            $this->logger->debug(
                'Get prices response built successfully.',
                ['context' => 'sep38', 'operation' => 'prices', 'result' => json_encode($result)],
            );

            return new JsonResponse(['buy_assets' => $result], 200);
        } catch (Throwable $t) {
            $this->logger->error(
                'Failed to build the get prices response.',
                ['context' => 'sep38', 'operation' => 'prices',
                    'error' => $t->getMessage(), 'exception' => $t, 'http_status_code' => 400,
                ],
            );

            return new JsonResponse(['error' => $t->getMessage()], 400);
        }
    }

    /**
     * Handles a GET /price request.
     *
     * This endpoint can be used to fetch the indicative price for a given asset pair.
     * These prices are indicative. The actual price will be calculated at conversion time once the Anchor
     * receives the funds from a User.
     * Fees can be collected by adding a margin to the price, and/or by deducting an amount from the funds
     * provided by or delivered to the client. Both the margin and the fees may vary depending on the
     * directional flow of funds, amount, delivery method, country code, context or other factors.
     *
     * See:
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md#get-price">GET price</a>
     *
     * @param ServerRequestInterface $request the request data from the client.
     * @param Sep10Jwt|null $jwtToken the jwt token obtained from SEP-10 if any.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handleGetPriceRequest(
        ServerRequestInterface $request,
        ?Sep10Jwt $jwtToken = null,
    ): ResponseInterface {
        try {
            // get account id and memo of the user from jwt token if provided.
            $accountId = null;
            $accountMemo = null;

            if ($jwtToken !== null) {
                $accountData = $jwtToken->getValidatedAccountData();
                if (isset($accountData['account_id'])) {
                    $accountId = $accountData['account_id'];
                }
                if (isset($accountData['account_memo'])) {
                    $accountMemo = $accountData['account_memo'];
                }
            }

            // read the query parameters from the request
            $queryParameters = $request->getQueryParams();
            $this->logger->debug(
                'Query parameters before processing.',
                ['context' => 'sep38', 'operation' => 'price',
                    'query_parameters' => json_encode($queryParameters),
                ],
            );

            // fetch the supported assets from the server integration.
            $supportedAssets = $this->sep38Integration->supportedAssets(
                accountId: $accountId,
                accountMemo: $accountMemo,
            );

            // validate the request data and build a server integration request object from it
            $request = Sep38RequestParser::getPriceRequestFromRequestData(
                requestData: $queryParameters,
                supportedAssets: $supportedAssets,
            );
            $this->logger->debug(
                'Parameters after processing.',
                ['context' => 'sep38', 'operation' => 'price', 'parameters' => json_encode($request)],
            );

            // attach user data extracted from jwt token if any
            $request->accountId = $accountId;
            $request->accountMemo = $accountMemo;

            // request the price from the server integration.
            $price = $this->sep38Integration->getPrice(request: $request);
            $responseJson = $price->toJson();
            $this->logger->debug(
                'Get price response built successfully.',
                ['context' => 'sep38', 'operation' => 'price', 'result' => json_encode($responseJson)],
            );

            return new JsonResponse($responseJson, 200);
        } catch (Throwable $t) {
            $this->logger->error(
                'Failed to build the get price response.',
                ['context' => 'sep38', 'operation' => 'price',
                    'error' => $t->getMessage(), 'exception' => $t, 'http_status_code' => 400,
                ],
            );

            return new JsonResponse(['error' => $t->getMessage()], 400);
        }
    }

    /**
     * Handles a POST /quote request.
     *
     * This endpoint can be used to request a firm quote for a Stellar asset and off-chain asset pair.
     * In contrast with the GET /price and GET /prices endpoints, the amount requested must be held in reserve and
     * not used in calculations of subsequent quotes until the expiration provided in the response.
     *
     * See:
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md#post-quote">POST quote</a>
     *
     * @param ServerRequestInterface $request the request data from the client.
     * @param Sep10Jwt $jwtToken the jwt token obtained from SEP-10.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handlePostQuoteRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            // parse the data from the request
            $requestData = $request->getParsedBody();

            // if data is not in getParsedBody(), try to parse with our own parser.
            if (!is_array($requestData) || count($requestData) === 0) {
                $this->logger->debug(
                    'The request body data is empty, try to parse with the SDK parser.',
                    ['context' => 'sep38', 'operation' => 'new_quote'],
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
                ['context' => 'sep38', 'operation' => 'new_quote',
                    'content' => json_encode($requestData),
                ],
            );

            // get account id and memo of the user from jwt token.
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
                    ['context' => 'sep38', 'operation' => 'new_quote', 'http_status_code' => 403],
                );

                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            // fetch the supported assets from the server integration.
            $supportedAssets = $this->sep38Integration->supportedAssets(
                accountId: $accountId,
                accountMemo: $accountMemo,
            );

            // validate the request data and build a server integration request object from it
            $request = Sep38RequestParser::getQuoteRequestFromRequestData(
                requestData: $requestData,
                supportedAssets: $supportedAssets,
                accountId: $accountId,
                accountMemo: $accountMemo,
            );
            $this->logger->debug(
                'Processed parameters.',
                ['context' => 'sep38', 'operation' => 'new_quote', 'request' => json_encode($request)],
            );

            // request the quote from the server integration.
            $quote = $this->sep38Integration->getQuote($request);
            $this->logger->debug(
                'The created quote.',
                ['context' => 'sep38', 'operation' => 'new_quote', 'quote' => json_encode($quote)],
            );

            return new JsonResponse($quote->toJson(), 201);
        } catch (Throwable $t) {
            $this->logger->error(
                'Failed to create new firm quote.',
                ['context' => 'sep38', 'operation' => 'new_quote',
                    'error' => $t->getMessage(), 'exception' => $t, 'http_status_code' => 400,
                ],
            );

            return new JsonResponse(['error' => $t->getMessage()], 400);
        }
    }

    /**
     * Handles a GET /quote (by id) request.
     *
     * This endpoint can be used to fetch a previously-provided firm quote. Quotes referenced in other protocols
     * must be available at this endpoint past the expires_at expiration for the quote.
     *
     * See:
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md#get-quote">GET quote</a>
     *
     * @param ServerRequestInterface $request the request data from the client.
     * @param Sep10Jwt $jwtToken the jwt token obtained from SEP-10.
     *
     * @return ResponseInterface the response to be sent back to the client.
     */
    private function handleGetQuoteRequest(
        ServerRequestInterface $request,
        Sep10Jwt $jwtToken,
    ): ResponseInterface {
        try {
            // get account id and memo of the user from jwt token.
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
                    ['context' => 'sep38', 'operation' => 'quote', 'http_status_code' => 403],
                );

                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            // extract the quote id from the request url
            $requestTarget = $request->getRequestTarget();
            $path = explode('/', $requestTarget);
            $quoteId = array_pop($path);

            if (trim($quoteId) === '') {
                throw new InvalidSepRequest('missing quote id in request');
            }
            $this->logger->debug(
                'Retrieving quote by id.',
                ['context' => 'sep38', 'operation' => 'quote', 'id' => $quoteId],
            );

            // request the quote from the server integration.
            $quote = $this->sep38Integration->getQuoteById(
                id:$quoteId,
                accountId: $accountId,
                accountMemo: $accountMemo,
            );
            $this->logger->debug(
                'The quote has been retrieved successfully.',
                ['context' => 'sep38', 'operation' => 'quote', 'quote' => json_encode($quote)],
            );

            return new JsonResponse($quote->toJson(), 200);
        } catch (QuoteNotFoundForId $qe) {
            $this->logger->error(
                'Quote not found.',
                ['context' => 'sep38', 'operation' => 'quote',
                    'error' => $qe->getMessage(), 'exception' => $qe, 'http_status_code' => 404,
                ],
            );

            return new JsonResponse(['error' => $qe->getMessage()], 404);
        } catch (Throwable $t) {
            $this->logger->error(
                'Failed to retrieve the quote.',
                ['context' => 'sep38', 'operation' => 'quote',
                    'error' => $t->getMessage(), 'exception' => $t, 'http_status_code' => 400,
                ],
            );

            return new JsonResponse(['error' => $t->getMessage()], 400);
        }
    }
}
