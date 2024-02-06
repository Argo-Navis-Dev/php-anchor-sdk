<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep24AssetInfo
{
    public IdentificationFormatAsset $asset;
    public DepositOperation $depositOperation;
    public WithdrawOperation $withdrawOperation;

    /**
     * @param IdentificationFormatAsset $asset the asset
     * @param DepositOperation $depositOperation deposit data for the asset
     * @param WithdrawOperation $withdrawOperation withdraw data for the asset
     */
    public function __construct(
        IdentificationFormatAsset $asset,
        DepositOperation $depositOperation,
        WithdrawOperation $withdrawOperation,
    ) {
        $this->asset = $asset;
        $this->depositOperation = $depositOperation;
        $this->withdrawOperation = $withdrawOperation;
    }
}
