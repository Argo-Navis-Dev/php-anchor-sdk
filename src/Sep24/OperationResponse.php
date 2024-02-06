<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep24;

use ArgoNavis\PhpAnchorSdk\shared\AssetOperation;

class OperationResponse
{
    public bool $enabled;
    public ?float $minAmount = null;
    public ?float $maxAmount = null;
    public ?float $feeFixed = null;
    public ?float $feePercent = null;
    public ?float $feeMinimum = null;

    /**
     * @param bool $enabled true if deposit/withdraw for this asset is supported
     * @param float|null $minAmount Optional minimum amount. No limit if not specified.
     * @param float|null $maxAmount Optional maximum amount. No limit if not specified.
     * @param float|null $feeFixed Optional fixed (base) fee for deposit/withdraw. In units of the deposited asset. This is in addition to any feePercent. Omit if there is no fee or the fee schedule is complex.
     * @param float|null $feePercent Optional percentage fee for deposit/withdraw. In percentage points. This is in addition to any feeFixed. Omit if there is no fee or the fee schedule is complex.
     * @param float|null $feeMinimum Optional minimum fee in units of the deposited/withdrawn asset.
     */
    public function __construct(
        bool $enabled,
        ?float $minAmount = null,
        ?float $maxAmount = null,
        ?float $feeFixed = null,
        ?float $feePercent = null,
        ?float $feeMinimum = null,
    ) {
        $this->enabled = $enabled;
        $this->minAmount = $minAmount;
        $this->maxAmount = $maxAmount;
        $this->feeFixed = $feeFixed;
        $this->feePercent = $feePercent;
        $this->feeMinimum = $feeMinimum;
    }

    public static function fromAssetOperation(AssetOperation $assetOp): OperationResponse
    {
        return new OperationResponse(
            $assetOp->enabled,
            $assetOp->minAmount,
            $assetOp->maxAmount,
            $assetOp->feeFixed,
            $assetOp->feePercent,
            $assetOp->feeMinimum,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $data
         */
        $data = [
            'enabled' => $this->enabled,
        ];

        if ($this->minAmount !== null) {
            $data += ['min_amount' => $this->minAmount];
        }

        if ($this->maxAmount !== null) {
            $data += ['max_amount' => $this->maxAmount];
        }

        if ($this->feeFixed !== null) {
            $data += ['fee_fixed' => $this->feeFixed];
        }

        if ($this->feePercent !== null) {
            $data += ['fee_percent' => $this->feePercent];
        }

        if ($this->feeMinimum !== null) {
            $data += ['fee_minimum' => $this->feeMinimum];
        }

        return $data;
    }
}
