<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\config;

interface ISep06Config
{
    /**
     * Whether the anchor supports creating accounts for users requesting deposits.
     *
     * @return bool true if anchor supports creating accounts for users requesting deposits.
     */
    public function isAccountCreationSupported(): bool;

    /**
     * Whether the anchor supports sending deposit funds as claimable balances.
     * This is relevant for users of Stellar accounts without a trustline to the requested asset.
     *
     * @return bool true if anchor supports sending deposit funds as claimable balances.
     */
    public function areClaimableBalancesSupported(): bool;
}
