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
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_pop;
use function count;
use function explode;
use function is_array;
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
     * @param IQuotesIntegration $sep38Integration the callback class providing the needed business
     *  logic. See IQuotesIntegration description.
     */
    public function __construct(IQuotesIntegration $sep38Integration)
    {
        $this->sep38Integration = $sep38Integration;
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
        if ($request->getMethod() === 'GET') {
            if (str_contains($requestTarget, '/info')) {
                return $this->handleGetInfoRequest(jwtToken: $jwtToken);
            } elseif (str_contains($requestTarget, '/prices')) {
                return $this->handleGetPricesRequest(request: $request, jwtToken: $jwtToken);
            } elseif (str_contains($requestTarget, '/price')) {
                return $this->handleGetPriceRequest(request: $request, jwtToken: $jwtToken);
            } elseif (str_contains($requestTarget, '/quote')) {
                if ($jwtToken === null) {
                    //403  forbidden
                    return new JsonResponse(['error' => 'SEP-10 authentication required'], 403);
                }

                return $this->handleGetQuoteRequest(request:$request, jwtToken: $jwtToken);
            }
        } elseif ($request->getMethod() === 'POST') {
            if (str_contains($requestTarget, '/quote')) {
                if ($jwtToken === null) {
                    //403  forbidden
                    return new JsonResponse(['error' => 'SEP-10 authentication required'], 403);
                }

                return $this->handlePostQuoteRequest(request:$request, jwtToken: $jwtToken);
            }
        } else {
            return new JsonResponse(['error' => 'Invalid request. Method not supported.'], 404);
        }

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

            return new JsonResponse(['assets' => $formattedAssets], 200);
        } catch (Throwable $t) {
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

            return new JsonResponse(['buy_assets' => $result], 200);
        } catch (Throwable $t) {
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

            // attach user data extracted from jwt token if any
            $request->accountId = $accountId;
            $request->accountMemo = $accountMemo;

            // request the price from the server integration.
            $price = $this->sep38Integration->getPrice(request: $request);

            return new JsonResponse($price->toJson(), 200);
        } catch (Throwable $t) {
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
                $requestData = RequestBodyDataParser::getParsedBodyData(
                    $request,
                    0,
                    0,
                );
                if ($requestData instanceof MultipartFormDataset) {
                    $requestData = $requestData->bodyParams;
                }
            }

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

            // request the quote from the server integration.
            $quote = $this->sep38Integration->getQuote($request);

            return new JsonResponse($quote->toJson(), 201);
        } catch (Throwable $t) {
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
                return new JsonResponse(['error' => 'invalid jwt token'], 403);
            }

            // extract the quote id from the request url
            $requestTarget = $request->getRequestTarget();
            $path = explode('/', $requestTarget);
            $quoteId = array_pop($path);

            if (trim($quoteId) === '') {
                throw new InvalidSepRequest('missing quote id in request');
            }

            // request the quote from the server integration.
            $quote = $this->sep38Integration->getQuoteById(
                id:$quoteId,
                accountId: $accountId,
                accountMemo: $accountMemo,
            );

            return new JsonResponse($quote->toJson(), 200);
        } catch (QuoteNotFoundForId $qe) {
            return new JsonResponse(['error' => $qe->getMessage()], 404);
        } catch (Throwable $t) {
            return new JsonResponse(['error' => $t->getMessage()], 400);
        }
    }
}
