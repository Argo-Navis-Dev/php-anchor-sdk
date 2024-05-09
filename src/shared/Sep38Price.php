<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep38Price
{
    /**
     * @var string $totalPrice The total conversion price offered by the anchor for one unit of buyAsset in terms
     * of sellAsset, including fees. In traditional finance, buyAsset would be referred to as the base asset
     * and sellAsset as the counter asset.
     */
    public string $totalPrice;

    /**
     * @var string $price The conversion price offered by the anchor for one unit of buyAsset in terms of sellAsset,
     * without including fees. In traditional finance, buyAsset would be referred to as the base asset and sellAsset
     * as the counter asset.
     */
    public string $price;

    /**
     * @var string $sellAmount The amount of sellAsset the anchor will exchange for buyAsset. It could be
     * different from the sellAmount provided in the request, depending on how fees are applied by the Anchor.
     */
    public string $sellAmount;

    /**
     * @var string $buyAmount The amount of buyAsset the anchor will provide with sellAsset. It could be different
     * from the buyAmount provided in the request, depending on how fees are applied by the Anchor.
     */
    public string $buyAmount;

    /**
     * @var TransactionFeeInfo $fee An object describing the fee used to calculate the conversion price. This can be used to
     * detail the price components for the end-user.
     */
    public TransactionFeeInfo $fee;

    /**
     * @param string $totalPrice The total conversion price offered by the anchor for one unit of buyAsset in terms
     * of sellAsset, including fees. In traditional finance, buyAsset would be referred to as the base asset
     * and sellAsset as the counter asset.
     * @param string $price The conversion price offered by the anchor for one unit of buyAsset in terms of sellAsset,
     * without including fees. In traditional finance, buyAsset would be referred to as the base asset and sellAsset
     * as the counter asset.
     * @param string $sellAmount The amount of sellAsset the anchor will exchange for buyAsset. It could be
     * different from the sellAmount provided in the request, depending on how fees are applied by the Anchor.
     * @param string $buyAmount The amount of buyAsset the anchor will provide with sellAsset. It could be different
     * from the buyAmount provided in the request, depending on how fees are applied by the Anchor.
     * @param TransactionFeeInfo $fee An object describing the fee used to calculate the conversion price. This can be used to
     * detail the price components for the end-user.
     */
    public function __construct(
        string $totalPrice,
        string $price,
        string $sellAmount,
        string $buyAmount,
        TransactionFeeInfo $fee,
    ) {
        $this->totalPrice = $totalPrice;
        $this->price = $price;
        $this->sellAmount = $sellAmount;
        $this->buyAmount = $buyAmount;
        $this->fee = $fee;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'total_price' => $this->totalPrice,
            'price' => $this->price,
            'sell_amount' => $this->sellAmount,
            'buy_amount' => $this->buyAmount,
            'fee' => $this->fee->toJson(),
        ];
    }
}
