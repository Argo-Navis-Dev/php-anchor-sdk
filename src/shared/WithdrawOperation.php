<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class WithdrawOperation extends AssetOperation
{
    /**
     * @param bool $enabled true if withdraw for this asset is supported
     * @param float|null $minAmount Optional minimum amount. No limit if not specified.
     * @param float|null $maxAmount Optional maximum amount. No limit if not specified.
     * @param float|null $feeFixed Optional fixed (base) fee for withdraw.
     * In units of the deposited asset. This is in addition to any feePercent.
     * Omit if there is no fee or the fee schedule is complex.
     * @param float|null $feePercent Optional percentage fee for deposit/withdraw.
     * In percentage points. This is in addition to any feeFixed.
     * Omit if there is no fee or the fee schedule is complex.
     * @param float|null $feeMinimum Optional minimum fee in units of the deposited/withdrawn asset.
     * @param array<string>|null $methods operation methods. Relevant for SEP-06.
     * E.g. for withdraw, type of withdrawal to make: ["bank_account", "cash"]
     */
    public function __construct(
        bool $enabled,
        ?float $minAmount = null,
        ?float $maxAmount = null,
        ?float $feeFixed = null,
        ?float $feePercent = null,
        ?float $feeMinimum = null,
        ?array $methods = null,
    ) {
        parent::__construct($enabled, $minAmount, $maxAmount, $feeFixed, $feePercent, $feeMinimum, $methods);
    }
}
