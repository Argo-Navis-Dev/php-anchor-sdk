<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep38BuyAsset
{
    /**
     * @var IdentificationFormatAsset $asset The Asset Identification Format value.
     */
    public IdentificationFormatAsset $asset;

    /**
     * @var string $price The price offered by the anchor for one unit of asset in terms of sell_asset.
     * In traditional finance, asset would be referred to as the base asset and sell_asset as the counter asset.
     */
    public string $price;

    /**
     * @var int $decimals The number of decimals needed to represent the asset.
     */
    public int $decimals;

    /**
     * @param IdentificationFormatAsset $asset The Asset Identification Format value.
     * @param string $price The price offered by the anchor for one unit of asset in terms of sell_asset.
     *  In traditional finance, asset would be referred to as the base asset and sell_asset as the counter asset.
     * @param int $decimals The number of decimals needed to represent the asset.
     */
    public function __construct(IdentificationFormatAsset $asset, string $price, int $decimals)
    {
        $this->asset = $asset;
        $this->price = $price;
        $this->decimals = $decimals;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'asset' => $this->asset->getStringRepresentation(),
            'price' => $this->price,
            'decimals' => $this->decimals,
        ];
    }
}
