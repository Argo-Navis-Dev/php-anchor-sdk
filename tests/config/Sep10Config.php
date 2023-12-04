<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\config;

use ArgoNavis\PhpAnchorSdk\config\ISep10Config;

class Sep10Config implements ISep10Config
{
    public function getWebAuthDomain(): ?string
    {
        return 'localhost:8000/auth';
    }

    /**
     * @inheritDoc
     */
    public function getHomeDomains(): array
    {
        return ['localhost:8000'];
    }

    public function getAuthTimeout(): int
    {
        return 300;
    }

    public function getJwtTimeout(): int
    {
        return 600;
    }

    public function isClientAttributionRequired(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedClientDomains(): ?array
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getKnownCustodialAccountList(): ?array
    {
        return null;
    }
}
