<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\config;

use ArgoNavis\PhpAnchorSdk\config\ISep24Config;

class Sep24Config implements ISep24Config
{
    public bool $feeEndpointEnabled = true;

    public function isAccountCreationSupported(): bool
    {
        return false;
    }

    public function areClaimableBalancesSupported(): bool
    {
        return false;
    }

    public function isFeeEndpointSupported(): bool
    {
        return $this->feeEndpointEnabled;
    }

    public function shouldSdkCalculateObviousFee(): bool
    {
        return true;
    }

    public function getUploadFileMaxSizeMb(): ?int
    {
        return null;
    }

    public function getUploadFileMaxCount(): ?int
    {
        return null;
    }
}
