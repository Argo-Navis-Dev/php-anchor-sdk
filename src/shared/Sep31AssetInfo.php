<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep31AssetInfo
{
    /**
     * @var IdentificationFormatAsset the asset, the info is for.
     */
    public IdentificationFormatAsset $asset;

    /**
     * @var array<Sep12Type> $sep12SenderTypes Sep12 sender types to be listed in info.
     */
    public array $sep12SenderTypes;

    /**
     * @var array<Sep12Type> $sep12ReceiverTypes Sep12 receiver types to be listed in info.
     */
    public array $sep12ReceiverTypes;

    /**
     * @var float|null $minAmount (optional) Minimum amount. No limit if not specified.
     */
    public ?float $minAmount = null;

    /**
     * @var float|null $maxAmount (optional) Maximum amount. No limit if not specified.
     */
    public ?float $maxAmount = null;

    /**
     * @var float|null $feeFixed (optional) A fixed fee in units of the Stellar asset.
     * Leave blank if there is no fee or fee calculation cannot be modeled using a fixed and percentage fee.
     */
    public ?float $feeFixed = null;

    /**
     * @var float|null $feePercent (optional) A percentage fee in percentage points.
     * Leave blank if there is no fee or fee calculation cannot be modeled using a fixed and percentage fee.
     */
    public ?float $feePercent = null;

    /**
     * @var bool|null $quotesSupported If true, the Receiving Anchor can deliver the off-chain assets listed in the SEP-38 GET
     *  /prices response in exchange for receiving the Stellar asset. Defaults to false.
     */
    public ?bool $quotesSupported = null;

    /**
     * @var bool|null $quotesRequired (optional) If true, the Receiving Anchor can only deliver an off-chain asset listed in the SEP-38 GET
     *  /prices response in exchange for receiving the Stellar asset. Defaults to false.
     */
    public ?bool $quotesRequired;

    /**
     * @param IdentificationFormatAsset $asset Supported asset.
     * @param array<Sep12Type> $sep12SenderTypes Sep12 sender types to be listed in info.
     * @param array<Sep12Type> $sep12ReceiverTypes Sep12 receiver types to be listed in info.
     * @param float|null $minAmount (optional) Minimum amount. No limit if not specified.
     * @param float|null $maxAmount (optional) Maximum amount. No limit if not specified.
     * @param float|null $feeFixed (optional) A fixed fee in units of the Stellar asset.
     *  Leave blank if there is no fee or fee calculation cannot be modeled using a fixed and percentage fee.
     * @param float|null $feePercent (optional) A percentage fee in percentage points.
     *  Leave blank if there is no fee or fee calculation cannot be modeled using a fixed and percentage fee
     * @param bool|null $quotesSupported If true, the Receiving Anchor can deliver the off-chain assets listed in the SEP-38 GET
     *   /prices response in exchange for receiving the Stellar asset. Defaults to false.
     * @param bool|null $quotesRequired (optional) If true, the Receiving Anchor can only deliver an off-chain asset listed in the SEP-38 GET
     *   /prices response in exchange for receiving the Stellar asset. Defaults to false.
     */
    public function __construct(
        IdentificationFormatAsset $asset,
        array $sep12SenderTypes,
        array $sep12ReceiverTypes,
        ?float $minAmount = null,
        ?float $maxAmount = null,
        ?float $feeFixed = null,
        ?float $feePercent = null,
        ?bool $quotesSupported = null,
        ?bool $quotesRequired = null,
    ) {
        $this->asset = $asset;
        $this->sep12SenderTypes = $sep12SenderTypes;
        $this->sep12ReceiverTypes = $sep12ReceiverTypes;
        $this->minAmount = $minAmount;
        $this->maxAmount = $maxAmount;
        $this->feeFixed = $feeFixed;
        $this->feePercent = $feePercent;
        $this->quotesSupported = $quotesSupported;
        $this->quotesRequired = $quotesRequired;
    }

    /**
     * Converts the object to an array.
     *
     * @return array<array-key, mixed> The JSON representation of the object.
     */
    public function toJson(): array
    {
        /**
         * @var array<array-key, mixed> $data
         */
        $data = [];

        if ($this->minAmount !== null) {
            $data['min_amount'] = $this->minAmount;
        }
        if ($this->maxAmount !== null) {
            $data['max_amount'] = $this->maxAmount;
        }
        if ($this->feeFixed !== null) {
            $data['fee_fixed'] = $this->feeFixed;
        }
        if ($this->feePercent !== null) {
            $data['fee_percent'] = $this->feePercent;
        }
        if ($this->quotesSupported !== null) {
            $data['quotes_supported'] = $this->quotesSupported;
        }
        if ($this->quotesRequired !== null) {
            $data['quotes_required'] = $this->quotesRequired;
        }

        /**
         * @var array<array-key, mixed> $senderTypesData
         */
        $senderTypesData = [];
        foreach ($this->sep12SenderTypes as $senderType) {
            $senderTypesData[$senderType->name] = ['description' => $senderType->description];
        }

        /**
         * @var array<array-key, mixed> $receiverTypesData
         */
        $receiverTypesData = [];
        foreach ($this->sep12ReceiverTypes as $receiverType) {
            $receiverTypesData[$receiverType->name] = ['description' => $receiverType->description];
        }
        $data['sep12'] = [
            'sender' => ['types' => $senderTypesData],
            'receiver' => ['types' => $receiverTypesData],
        ];

        return $data;
    }
}
