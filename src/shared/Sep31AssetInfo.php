<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep31AssetInfo
{
    /**
     * Sender and receiver asset key.
     */
    private string $asset;

    /**
     * (optional) If true, the Receiving Anchor can deliver the off-chain assets listed in the SEP-38 GET
     * /prices response in exchange for receiving the Stellar asset.
     */
    private ?bool $quotesSupported;
    /**
     * (optional) If true, the Receiving Anchor can only deliver an off-chain asset listed in the SEP-38 GET
     * /prices response in exchange for receiving the Stellar asset.
     */
    private ?bool $quotesRequired;
    /**
     * (optional) A fixed fee in units of the Stellar asset.
     * Leave blank if there is no fee or fee calculation cannot be modeled using a fixed and percentage fee.
     */
    private ?float $feeFixed;
    /**
     * (optional) A percentage fee in percentage points.
     * Leave blank if there is no fee or fee calculation cannot be modeled using a fixed and percentage fee.
     */
    private ?float $feePercent;
    /**
     * (optional) Minimum amount. No limit if not specified.
     */
    private ?float $minAmount;
    /**
     * (optional) Maximum amount. No limit if not specified.
     */
    private ?float $maxAmount;

    private Sep12TypesInfo $sep12;

    public function __construct(
        string $asset,
        bool | null $quotesSupported,
        bool | null $quotesRequired,
        float | null $feeFixed,
        float | null $feePercent,
        float | null $minAmount,
        float | null $maxAmount,
        Sep12TypesInfo $sep12,
    ) {
        $this->asset = $asset;
        $this->quotesSupported = $quotesSupported;
        $this->quotesRequired = $quotesRequired;
        $this->feeFixed = $feeFixed;
        $this->feePercent = $feePercent;
        $this->minAmount = $minAmount;
        $this->maxAmount = $maxAmount;
        $this->sep12 = $sep12;
    }

    /**
     * Converts the object to an array.
     *
     * @return array<string, mixed> The array representation of the object.
     */
    public function toJson(): array
    {
        $result = [];
        $result['quotes_supported'] = $this->quotesSupported;
        $result['quotes_required'] = $this->quotesRequired;
        $result['fee_fixed'] = $this->feeFixed;
        $result['fee_percent'] = $this->feePercent;
        $result['min_amount'] = $this->minAmount;
        $result['max_amount'] = $this->maxAmount;
        $result['sep12'] = $this->sep12->toJson();

        return $result;
    }

    public function getAsset(): string
    {
        return $this->asset;
    }
}
