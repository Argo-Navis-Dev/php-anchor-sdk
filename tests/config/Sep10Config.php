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
        return 'localhost:8000';
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

    public function getSep10SigningSeed(): string
    {
        return 'SCYJJBZTHTN2RZI7UA2MN3RNMSDNQ3BKHPYWXXPXMRJ4KLU7N5XQ5BXE';
        // GA4A5CVA2QJNS5CBPOEFKWJC4F5SUI36IPWHAKIEKBQ7UVGJ4Y5WC5FA
    }

    public function getSep10JWTSigningKey(): string
    {
        return 'SDY6IQYPXU2XYUUCYJML6M2UUGTGSWXCACAKJ55DG5JG7QVH5CD26K7I';
        // GBUANI7GNVM4EQOWEKBMJFL3O4C6PCY3JL7JHM7LEMKIJVEQM4YLJ7PE
    }
}
