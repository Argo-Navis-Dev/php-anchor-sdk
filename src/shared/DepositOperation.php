<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class DepositOperation extends AssetOperation
{
    public function __construct(
        bool $enabled,
        ?float $minAmount = null,
        ?float $maxAmount = null,
        ?float $feeFixed = null,
        ?float $feePercent = null,
        ?float $feeMinimum = null,
    ) {
        parent::__construct($enabled, $minAmount, $maxAmount, $feeFixed, $feePercent, $feeMinimum);
    }
}
