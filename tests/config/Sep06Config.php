<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\config;

use ArgoNavis\PhpAnchorSdk\config\ISep06Config;

class Sep06Config implements ISep06Config
{
    public function isAccountCreationSupported(): bool
    {
        return false;
    }

    public function areClaimableBalancesSupported(): bool
    {
        return false;
    }
}
