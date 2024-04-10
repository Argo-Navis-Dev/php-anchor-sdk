<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\QuoteNotFoundForId;
use ArgoNavis\PhpAnchorSdk\shared\Sep38AssetInfo;
use ArgoNavis\PhpAnchorSdk\shared\Sep38BuyAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep38Price;
use ArgoNavis\PhpAnchorSdk\shared\Sep38Quote;

/**
 * The interface for the sep-38 endpoints of the callback API.
 */
interface IQuotesIntegration
{
    /**
     * Returns all assets supported by the anchor.
     *
     * @param string|null $accountId account id of the user if authenticated by SEP 10.
     * @param string|null $accountMemo account memo of the user if authenticated by SEP 10 and provided.
     *
     * @return array<Sep38AssetInfo> the list of supported assets.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function supportedAssets(?string $accountId = null, ?string $accountMemo = null): array;

    /**
     * Returns the indicative prices of available off-chain assets in exchange for a Stellar asset and vice versa.
     *
     * @param Sep38PricesRequest $request The request data of the GET /prices request.
     *
     * @return array<Sep38BuyAsset> An array of objects containing information on the assets that the client
     * will receive when they provide sellAsset.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function getPrices(Sep38PricesRequest $request): array;

    /**
     * Returns the indicative price for a given asset pair.
     *
     * @param Sep38PriceRequest $request The request data.
     *
     * @return Sep38Price The response containing the price info.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function getPrice(Sep38PriceRequest $request): Sep38Price;

    /**
     * Returns a firm quote for a Stellar asset and off-chain asset pair.
     *
     * @param Sep38QuoteRequest $request The request data.
     *
     * @return Sep38Quote The quote data.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function getQuote(Sep38QuoteRequest $request): Sep38Quote;

    /**
     * Returns a previously-provided firm quote by id.
     *
     * @param string $id id of the quote.
     * @param string $accountId account id of the user authenticated by SEP 10.
     * @param string|null $accountMemo (optional) account memo of the user authenticated by SEP 10.
     *  If available it should be used together with the $accountId to identify the user.
     *
     * @return Sep38Quote The quote data.
     *
     * @throws QuoteNotFoundForId if the quote could not be found for the given id.
     * @throws AnchorFailure if any other error occurs.
     */
    public function getQuoteById(string $id, string $accountId, ?string $accountMemo = null): Sep38Quote;
}
