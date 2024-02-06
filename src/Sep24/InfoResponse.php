<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep24;

use function array_keys;

class InfoResponse
{
    /**
     * @var array<string, OperationResponse> deposit assets.
     */
    public array $deposit;
    /**
     * @var array<string, OperationResponse> withdraw assets.
     */
    public array $withdraw;

    public FeeResponse $fee;

    public FeaturesFlagResponse $features;

    /**
     * @param array<string, OperationResponse> $deposit deposit assets.
     * @param array<string, OperationResponse> $withdraw withdraw assets.
     * @param FeeResponse $fee fee enabled or not.
     * @param FeaturesFlagResponse $features features.
     */
    public function __construct(array $deposit, array $withdraw, FeeResponse $fee, FeaturesFlagResponse $features)
    {
        $this->deposit = $deposit;
        $this->withdraw = $withdraw;
        $this->fee = $fee;
        $this->features = $features;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $data
         */
        $data = [];
        $depositData = [];
        foreach (array_keys($this->deposit) as $key) {
            $depositData += [$key => $this->deposit[$key]->toJson()];
        }

        $data += ['deposit' => $depositData];

        $withdrawData = [];
        foreach (array_keys($this->withdraw) as $key) {
            $withdrawData += [$key => $this->withdraw[$key]->toJson()];
        }

        $data += ['withdraw' => $withdrawData];

        $data += ['fee' => $this->fee->toJson()];
        $data += ['features' => $this->features->toJson()];

        return $data;
    }
}
