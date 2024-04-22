<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep31;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\callback\ICrossBorderPaymentsIntegration;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function is_string;
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
    public ICrossBorderPaymentsIntegration $sep31Integration;

    public function __construct(ICrossBorderPaymentsIntegration $sep31Integration)
    {
        $this->sep31Integration = $sep31Integration;
    }

    /**
     * Handles a forwarded client request specified by SEP-31. Builds and returns the corresponding response,
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
            $lang = $this->getRequestLang($request);
            if (str_contains($requestTarget, '/info')) {
                return $this->handleGetInfoRequest($lang);
            }
        }

        return new JsonResponse(['error' => 'Invalid request. Unknown endpoint.'], 200);
    }

    /**
     * Handles a GET /info request.
     * This endpoint describes the supported Stellar assets which the Anchor can receive.
     *
     * See:
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0031.md#get-info">GET info</a>
     *
     * @param string|null $lang the language code for a localized response.
     *
     * @return JsonResponse response to be sent back to the client
     */
    private function handleGetInfoRequest(?string $lang = null): JsonResponse
    {
        try {
            $supportedAssets = $this->sep31Integration->supportedAssets($lang);
            $response = [];
            foreach ($supportedAssets as $asset) {
                $response[$asset->getAsset()] = $asset->toJson();
            }

            return new JsonResponse(['receive' => $response], 200);
        } catch (Throwable $t) {
            return new JsonResponse(['error' => $t->getMessage()], 400);
        }
    }

    /**
     * Extracts the lang query parameter from the request.
     *
     * @param ServerRequestInterface $request the request from the client as defined in
     * <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>.
     *
     * @return string|null the lang query parameter or null if not present.
     *
     * @throws InvalidSepRequest if the lang query parameter is not a string.
     */
    private function getRequestLang(ServerRequestInterface $request): string | null
    {
        $queryParams = $request->getQueryParams();
        $lang = null;
        if (isset($queryParams['lang'])) {
            if (is_string($queryParams['lang'])) {
                $lang = $queryParams['lang'];
            } else {
                throw new InvalidSepRequest('lang must be a string');
            }
        }

        return $lang;
    }
}
