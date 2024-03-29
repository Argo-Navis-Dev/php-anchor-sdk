<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

use DateTime;
use DateTimeInterface;

class Sep38Quote
{
    /**
     * @var string $id The unique identifier for the quote to be used in other Stellar Ecosystem Proposals (SEPs).
     */
    public string $id;

    /**
     * @var DateTime $expiresAt The date and time by which the anchor must receive funds from the client.
     */
    public DateTime $expiresAt;

    /**
     * @var string $totalPrice The total conversion price offered by the anchor for one unit of buyAsset in terms
     * of sellAsset, including fees. In traditional finance, buyAsset would be referred to as the base asset and
     * sellAsset as the counter asset.
     */
    public string $totalPrice;

    /**
     * @var string $price The conversion price offered by the anchor for one unit of buyAsset in terms of sellAsset,
     * without including fees. In traditional finance, buyAsset would be referred to as the base asset and sellAsset
     * as the counter asset.
     */
    public string $price;

    /**
     * @var IdentificationFormatAsset $sellAsset The asset the client would like to sell.
     */
    public IdentificationFormatAsset $sellAsset;

    /**
     * @var string $sellAmount The amount of sellAsset to be exchanged for buyAsset. It could be different from
     * the sellAmount provided in the request, depending on how fees are applied by the Anchor.
     */
    public string $sellAmount;

    /**
     * @var IdentificationFormatAsset $buyAsset The asset the client would like to exchange for sellAsset.
     */
    public IdentificationFormatAsset $buyAsset;

    /**
     * @var string $buyAmount The amount of buyAsset to be exchanged for sellAsset. It could be different from
     * the buyAmount provided in the request, depending on how fees are applied by the Anchor.
     * price * buyAmount = sellAmount must be true up to the number of decimals required for buyAsset
     */
    public string $buyAmount;

    /**
     * @var Sep38Fee $fee An object describing the fee used to calculate the conversion price. This can be used to
     * detail the price components for the end-user.
     */
    public Sep38Fee $fee;

    /**
     * @param string $id The unique identifier for the quote to be used in other Stellar Ecosystem Proposals (SEPs).
     * @param DateTime $expiresAt The date and time by which the anchor must receive funds from the client.
     * @param string $totalPrice The total conversion price offered by the anchor for one unit of buyAsset in terms
     * of sellAsset, including fees. In traditional finance, buyAsset would be referred to as the base asset and
     * sellAsset as the counter asset.
     * @param string $price The conversion price offered by the anchor for one unit of buyAsset in terms of sellAsset,
     * without including fees. In traditional finance, buyAsset would be referred to as the base asset and sellAsset
     * as the counter asset.
     * @param IdentificationFormatAsset $sellAsset The asset the client would like to sell.
     * @param string $sellAmount The amount of sellAsset to be exchanged for buyAsset. It could be different from
     * the sellAmount provided in the request, depending on how fees are applied by the Anchor.
     * @param IdentificationFormatAsset $buyAsset The asset the client would like to exchange for sellAsset.
     * @param string $buyAmount The amount of buyAsset to be exchanged for sellAsset. It could be different from
     * the buyAmount provided in the request, depending on how fees are applied by the Anchor.
     * price * buyAmount = sellAmount must be true up to the number of decimals required for buyAsset
     * @param Sep38Fee $fee An object describing the fee used to calculate the conversion price. This can be used to
     * detail the price components for the end-user.
     */
    public function __construct(
        string $id,
        DateTime $expiresAt,
        string $totalPrice,
        string $price,
        IdentificationFormatAsset $sellAsset,
        string $sellAmount,
        IdentificationFormatAsset $buyAsset,
        string $buyAmount,
        Sep38Fee $fee,
    ) {
        $this->id = $id;
        $this->expiresAt = $expiresAt;
        $this->totalPrice = $totalPrice;
        $this->price = $price;
        $this->sellAsset = $sellAsset;
        $this->sellAmount = $sellAmount;
        $this->buyAsset = $buyAsset;
        $this->buyAmount = $buyAmount;
        $this->fee = $fee;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'id' => $this->id,
            'expires_at' => $this->expiresAt->format(DateTimeInterface::ATOM),
            'total_price' => $this->totalPrice,
            'price' => $this->price,
            'sell_asset' => $this->sellAsset->getStringRepresentation(),
            'sell_amount' => $this->sellAmount,
            'buy_asset' => $this->buyAsset->getStringRepresentation(),
            'buy_amount' => $this->buyAmount,
            'fee' => $this->fee->toJson(),
        ];
    }
}
