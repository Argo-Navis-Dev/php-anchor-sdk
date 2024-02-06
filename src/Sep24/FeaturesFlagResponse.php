<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep24;

class FeaturesFlagResponse
{
    public bool $accountCreation;
    public bool $claimableBalances;

    public function __construct(bool $accountCreation, bool $claimableBalances)
    {
        $this->accountCreation = $accountCreation;
        $this->claimableBalances = $claimableBalances;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'account_creation' => $this->accountCreation,
            'claimable_balances' => $this->claimableBalances,
        ];
    }
}
