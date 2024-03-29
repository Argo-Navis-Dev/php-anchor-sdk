<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;

class Sep38PriceRequest
{
    /**
     * @var string $context The context for what this quote will be used for. One of sep6 or sep31.
     */
    public string $context;

    /**
     * @var IdentificationFormatAsset $sellAsset The asset the client would like to sell.
     */
    public IdentificationFormatAsset $sellAsset;

    /**
     * @var IdentificationFormatAsset $buyAsset The asset the client would like to exchange for sellAsset.
     */
    public IdentificationFormatAsset $buyAsset;

    /**
     * @var string|null $sellAmount The amount of sellAsset the client would like to exchange for buyAsset.
     */
    public ?string $sellAmount = null;

    /**
     * @var string|null $buyAmount The amount of buyAsset the client would like to purchase with sellAsset.
     */
    public ?string $buyAmount = null;

    /**
     * @var string|null $sellDeliveryMethod (optional) One of the name values specified by the sell_delivery_methods
     * array for the associated, supported asset returned from GET /info. Can be provided if the user is delivering an
     * off-chain asset to the anchor but is not strictly required.
     */
    public ?string $sellDeliveryMethod = null;

    /**
     * @var string|null $buyDeliveryMethod (optional) One of the name values specified by the buy_delivery_methods
     * array for the associated, supported asset returned from GET /info. Can be provided if the user intends to
     * receive an off-chain asset from the anchor but is not strictly required.
     */
    public ?string $buyDeliveryMethod = null;

    /**
     * @var string|null $countryCode (optional) The ISO 3166-2 or ISO-3166-1 alpha-2 code of the user's current address.
     * Should be provided if there are two or more country codes available for the desired asset in GET /info.
     */
    public ?string $countryCode = null;

    /**
     * @var string|null $accountId account id of the user if authenticated by SEP 10.
     * If available it can be used to personalize the response.
     */
    public ?string $accountId = null;

    /**
     * @var string|null $accountMemo account memo of the user if authenticated and provided by SEP 10.
     * If available it should be used together with the $accountId to identify the user.
     * Then it can be used to personalize the response.
     */
    public ?string $accountMemo = null;

    /**
     * @param string $context The context for what this quote will be used for. Must be one of sep6 or sep31.
     * @param IdentificationFormatAsset $sellAsset The amount of sellAsset the client would like to exchange for
     * buyAsset.
     * @param IdentificationFormatAsset $buyAsset The amount of buyAsset the client would like to purchase with
     * sellAsset.
     * @param string|null $sellAmount The amount of sellAsset the client would like to exchange for buyAsset.
     * @param string|null $buyAmount The amount of buyAsset the client would like to purchase with sellAsset.
     * @param string|null $sellDeliveryMethod (optional) One of the name values specified by the sell_delivery_methods
     * array for the associated, supported asset returned from GET /info. Can be provided if the user is delivering an
     * off-chain asset to the anchor but is not strictly required.
     * @param string|null $buyDeliveryMethod (optional) One of the name values specified by the buy_delivery_methods
     * array for the associated, supported asset returned from GET /info. Can be provided if the user intends to
     * receive an off-chain asset from the anchor but is not strictly required.
     * @param string|null $countryCode (optional) The ISO 3166-2 or ISO-3166-1 alpha-2 code of the user's current
     * address. Should be provided if there are two or more country codes available for the desired asset in GET /info.
     * @param string|null $accountId (optional) account id of the user if authenticated by SEP 10.
     * If available it can be used to personalize the response.
     * @param string|null $accountMemo (optional) account memo of the user if authenticated and provided by SEP 10.
     * If available it should be used together with the $accountId to identify the user. Then it can be used to
     * personalize the response.
     */
    public function __construct(
        string $context,
        IdentificationFormatAsset $sellAsset,
        IdentificationFormatAsset $buyAsset,
        ?string $sellAmount = null,
        ?string $buyAmount = null,
        ?string $sellDeliveryMethod = null,
        ?string $buyDeliveryMethod = null,
        ?string $countryCode = null,
        ?string $accountId = null,
        ?string $accountMemo = null,
    ) {
        $this->context = $context;
        $this->sellAsset = $sellAsset;
        $this->buyAsset = $buyAsset;
        $this->sellAmount = $sellAmount;
        $this->buyAmount = $buyAmount;
        $this->sellDeliveryMethod = $sellDeliveryMethod;
        $this->buyDeliveryMethod = $buyDeliveryMethod;
        $this->countryCode = $countryCode;
        $this->accountId = $accountId;
        $this->accountMemo = $accountMemo;
    }
}
