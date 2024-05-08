<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\InstructionsField;

class StartDepositResponse
{
    /**
     * @var string $id The anchor's ID for this deposit. The wallet will use this ID to query the
     * /transaction endpoint to check status of the request.
     */
    public string $id;

    /**
     * @var array<InstructionsField>|null $instructions (optional) an array containing the SEP-9 financial account
     * fields that describe how to complete the off-chain deposit.
     */
    public ?array $instructions = null;

    /**
     * @var int|null $eta (optional) Estimate of how long the deposit will take to credit in seconds.
     */
    public ?int $eta = null;

    /**
     * @var float|null $minAmount (optional) Minimum amount of an asset that a user can deposit.
     */
    public ?float $minAmount = null;

    /**
     * @var float|null (optional) Maximum amount of asset that a user can deposit.
     */
    public ?float $maxAmount = null;

    /**
     * @var float|null $feeFixed (optional) Fixed fee (if any). In units of the deposited asset.
     */
    public ?float $feeFixed = null;
    /**
     * @var float|null $feePercent (optional) Percentage fee (if any). In units of percentage points.
     */
    public ?float $feePercent = null;
    /**
     * @var string|null (optional) Additional details about the deposit process.
     */
    public ?string $extraInfo = null;

    /**
     * @var string|null $how (Deprecated, use instructions instead) Terse but complete instructions for how to deposit
     * the asset. In the case of most cryptocurrencies it is just an address to which the deposit should be sent.
     */
    public ?string $how = null;

    /**
     * @param string $id The anchor's ID for this deposit. The wallet will use this ID to query the
     *  /transaction endpoint to check status of the request.
     * @param array<InstructionsField>|null $instructions (optional) an array containing the SEP-9 financial account
     *  fields that describe how to complete the off-chain deposit.
     * @param int|null $eta (optional) Estimate of how long the deposit will take to credit in seconds.
     * @param float|null $minAmount (optional) Minimum amount of an asset that a user can deposit.
     * @param float|null $maxAmount (optional) Maximum amount of asset that a user can deposit.
     * @param float|null $feeFixed (optional) Fixed fee (if any). In units of the deposited asset.
     * @param float|null $feePercent (optional) Percentage fee (if any). In units of percentage points.
     * @param string|null $extraInfo (optional) JSON object with additional information about the deposit process.
     * @param string|null $how (Deprecated, use instructions instead) Terse but complete instructions for how to deposit
     *  the asset. In the case of most cryptocurrencies it is just an address to which the deposit should be sent.
     */
    public function __construct(
        string $id,
        ?array $instructions = null,
        ?int $eta = null,
        ?float $minAmount = null,
        ?float $maxAmount = null,
        ?float $feeFixed = null,
        ?float $feePercent = null,
        ?string $extraInfo = null,
        ?string $how = null,
    ) {
        $this->id = $id;
        $this->instructions = $instructions;
        $this->eta = $eta;
        $this->minAmount = $minAmount;
        $this->maxAmount = $maxAmount;
        $this->feeFixed = $feeFixed;
        $this->feePercent = $feePercent;
        $this->extraInfo = $extraInfo;
        $this->how = $how;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $json
         */
        $json = ['id' => $this->id];

        if ($this->instructions !== null) {
            /**
             * @var array<array-key, mixed> $fields
             */
            $fields = [];
            foreach ($this->instructions as $field) {
                $fields += [$field->name => ['value' => $field->value, 'description' => $field->description]];
            }
            $json['instructions'] = $fields;
        }

        if ($this->how !== null) {
            $json['how'] = $this->how;
        }

        if ($this->eta !== null) {
            $json['eta'] = $this->eta;
        }

        if ($this->minAmount !== null) {
            $json['min_amount'] = $this->minAmount;
        }

        if ($this->maxAmount !== null) {
            $json['max_amount'] = $this->maxAmount;
        }

        if ($this->feeFixed !== null) {
            $json['fee_fixed'] = $this->feeFixed;
        }

        if ($this->feePercent !== null) {
            $json['fee_percent'] = $this->feePercent;
        }

        if ($this->extraInfo !== null) {
            $json['extra_info'] = ['message' => $this->extraInfo];
        }

        return $json;
    }
}
