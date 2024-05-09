<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class DepositOperation extends AssetOperation
{
    /**
     * @param bool $enabled true if deposit for this asset is supported
     * @param float|null $minAmount Optional minimum amount. No limit if not specified.
     * @param float|null $maxAmount Optional maximum amount. No limit if not specified.
     * @param float|null $feeFixed Optional fixed (base) fee for deposit. In units of the deposited asset.
     * This is in addition to any feePercent. Omit if there is no fee or the fee schedule is complex.
     * @param float|null $feePercent Optional percentage fee for deposit. In percentage points.
     * This is in addition to any feeFixed. Omit if there is no fee or the fee schedule is complex.
     * @param float|null $feeMinimum Optional minimum fee in units of the deposited asset.
     * @param array<string>|null $methods operation methods. Relevant for SEP-06.
     *   E.g. for deposit, type of deposit to make: ["SEPA", "SWIFT", "cash"]
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
