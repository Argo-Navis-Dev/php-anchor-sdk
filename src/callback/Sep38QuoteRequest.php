<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use DateTime;

class Sep38QuoteRequest
{
    /**
     * @var string $context The context for what this quote will be used for. Must be one of sep6, sep24 or sep31.
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
     * @var string $accountId account id of the user authenticated by SEP 10.
     */
    public string $accountId;

    /**
     * @var string|null $accountMemo (optional) account memo of the user authenticated by SEP 10.
     * If available it should be used together with the $accountId to identify the user.
     */
    public ?string $accountMemo = null;

    /**
     * @var string|null $sellAmount The amount of sellAsset the client would like to exchange for buyAsset.
     */
    public ?string $sellAmount = null;

    /**
     * @var string|null $buyAmount The amount of buyAsset the client would like to purchase with sellAsset.
     */
    public ?string $buyAmount = null;

    /**
     * @var DateTime|null $expireAfter (optional) The client's desired expiresAt date and time for the quote.
     * Anchors may choose an expiresAt that occurs after the expireAfter. Anchors should return 400 Bad Request
     * if the expiration on or after the requested value cannot be provided.
     */
    public ?DateTime $expireAfter = null;
    /**
     * @var string|null $sellDeliveryMethod (optional) One of the name values specified by the
     * sellDeliveryMethods array for the associated, supported asset returned from GET /info. Can be provided if the
     * user is delivering an off-chain asset to the anchor but is not strictly required.
     */
    public ?string $sellDeliveryMethod = null;

    /**
     * @var string|null $buyDeliveryMethod (optional) One of the name values specified by the
     * buyDeliveryMethods array for the associated, supported asset returned from GET /info. Can be provided if the
     * user intends to receive an off-chain asset from the anchor but is not strictly required.
     */
    public ?string $buyDeliveryMethod = null;

    /**
     * @var string|null $countryCode (optional) The ISO 3166-2 or ISO-3166-1 alpha-2 code of the user's current address.
     * Should be provided if there are two or more country codes available for the desired asset in GET /info.
     */
    public ?string $countryCode = null;

    /**
     * @param string $context The context for what this quote will be used for. Must be one of sep6, sep24 or sep31
     * @param IdentificationFormatAsset $sellAsset The asset the client would like to sell.
     * @param IdentificationFormatAsset $buyAsset The asset the client would like to exchange for sellAsset.
     * @param string $accountId account id of the user authenticated by SEP 10.
     * @param string|null $accountMemo (optional) account memo of the user authenticated by SEP 10.
     * If available it should be used together with the $accountId to identify the user.
     * @param string|null $sellAmount The amount of sellAsset the client would like to exchange for buyAsset.
     * @param string|null $buyAmount The amount of buyAsset the client would like to purchase with sellAsset.
     * @param DateTime|null $expireAfter (optional) The client's desired expiresAt date and time for the quote.
     *  Anchors may choose an expiresAt that occurs after the expireAfter. Anchors should return 400 Bad Request
     *  if the expiration on or after the requested value cannot be provided.
     * @param string|null $sellDeliveryMethod (optional) One of the name values specified by the
     *  sellDeliveryMethods array for the associated, supported asset returned from GET /info. Can be provided if the
     *  user is delivering an off-chain asset to the anchor but is not strictly required.
     * @param string|null $buyDeliveryMethod (optional) One of the name values specified by the
     *  buyDeliveryMethods array for the associated, supported asset returned from GET /info. Can be provided if the
     *  user intends to receive an off-chain asset from the anchor but is not strictly required.
     * @param string|null $countryCode (optional) The ISO 3166-2 or ISO-3166-1 alpha-2 code of the user's current address.
     *  Should be provided if there are two or more country codes available for the desired asset in GET /inf
     */
    public function __construct(
        string $context,
        IdentificationFormatAsset $sellAsset,
        IdentificationFormatAsset $buyAsset,
        string $accountId,
        ?string $accountMemo = null,
        ?string $sellAmount = null,
        ?string $buyAmount = null,
        ?DateTime $expireAfter = null,
        ?string $sellDeliveryMethod = null,
        ?string $buyDeliveryMethod = null,
        ?string $countryCode = null,
    ) {
        $this->context = $context;
        $this->sellAsset = $sellAsset;
        $this->buyAsset = $buyAsset;
        $this->accountId = $accountId;
        $this->accountMemo = $accountMemo;
        $this->sellAmount = $sellAmount;
        $this->buyAmount = $buyAmount;
        $this->expireAfter = $expireAfter;
        $this->sellDeliveryMethod = $sellDeliveryMethod;
        $this->buyDeliveryMethod = $buyDeliveryMethod;
        $this->countryCode = $countryCode;
    }
}
