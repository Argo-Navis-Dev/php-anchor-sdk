<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep06AssetInfo
{
    /**
     * @var IdentificationFormatAsset the asset
     */
    public IdentificationFormatAsset $asset;

    /**
     * @var DepositOperation $depositOperation deposit data for the asset.
     */
    public DepositOperation $depositOperation;

    /**
     * @var bool true if deposit exchange is supported for this asset.
     */
    public bool $depositExchangeEnabled = false;

    /**
     * @var WithdrawOperation $withdrawOperation withdraw data for the asset.
     */
    public WithdrawOperation $withdrawOperation;

    /**
     * @var bool true if withdraw exchange is supported for this asset.
     */
    public bool $withdrawExchangeEnabled = false;

    /**
     * @param IdentificationFormatAsset $asset the asset
     * @param DepositOperation $depositOperation deposit data for the asset.
     * @param WithdrawOperation $withdrawOperation withdraw data for the asset.
     * @param bool|null $depositExchangeEnabled set to true if deposit exchange is supported for this asset. Defaults to false.
     * @param bool|null $withdrawExchangeEnabled set to true if withdraw exchange is supported for this asset. Defaults to false.
     */
    public function __construct(
        IdentificationFormatAsset $asset,
        DepositOperation $depositOperation,
        WithdrawOperation $withdrawOperation,
        ?bool $depositExchangeEnabled = false,
        ?bool $withdrawExchangeEnabled = false,
    ) {
        $this->asset = $asset;
        $this->depositOperation = $depositOperation;
        $this->depositExchangeEnabled = $depositExchangeEnabled ?? false;
        $this->withdrawOperation = $withdrawOperation;
        $this->withdrawExchangeEnabled = $withdrawExchangeEnabled ?? false;
    }
}
