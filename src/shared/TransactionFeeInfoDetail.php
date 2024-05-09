<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class TransactionFeeInfoDetail
{
    /**
     * @var string $name The name of the fee, for example ACH fee, Brazilian conciliation fee, Service fee, etc.
     */
    public string $name;
    /**
     * @var string $amount The amount of asset applied. If $fee->details is provided, sum($fee->details->amount)
     * should be equal to $fee->total.
     */
    public string $amount;

    /**
     * @var string|null (optional) A text describing the fee.
     */
    public ?string $description = null;

    /**
     * @param string $name The name of the fee, for example ACH fee, Brazilian conciliation fee, Service fee, etc.
     * @param string $amount The amount of asset applied. If $fee->details is provided, sum($fee->details->amount)
     *  should be equal to $fee->total.
     * @param string|null $description (optional) A text describing the fee.
     */
    public function __construct(
        string $name,
        string $amount,
        ?string $description = null,
    ) {
        $this->name = $name;
        $this->amount = $amount;
        $this->description = $description;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $result
         */
        $result = [
            'name' => $this->name,
            'amount' => $this->amount,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }
}
