<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\config;

use ArgoNavis\PhpAnchorSdk\config\ISep10Config;

class Sep10Config implements ISep10Config
{
    /**
     * @var array<string>
     */
    public array $homeDomains = [];
    public string $sep10SigningSeed = '';

    public string $sep10JwtSigningSeed = 'SDY6IQYPXU2XYUUCYJML6M2UUGTGSWXCACAKJ55DG5JG7QVH5CD26K7I';
    // GBUANI7GNVM4EQOWEKBMJFL3O4C6PCY3JL7JHM7LEMKIJVEQM4YLJ7PE
    /**
     * @var array<string>|null
     */
    public ?array $custodialAccountList = null;

    public bool $clientAttributionRequired = false;

    /**
     * @var array<string>|null
     */
    public ?array $allowedClientDomains = null;

    public function getWebAuthDomain(): ?string
    {
        return 'localhost:8000';
    }

    /**
     * @inheritDoc
     */
    public function getHomeDomains(): array
    {
        return $this->homeDomains;
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
        return $this->clientAttributionRequired;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedClientDomains(): ?array
    {
        return $this->allowedClientDomains;
    }

    /**
     * @inheritDoc
     */
    public function getKnownCustodialAccountList(): ?array
    {
        return $this->custodialAccountList;
    }

    public function getSep10SigningSeed(): string
    {
        return $this->sep10SigningSeed;
    }

    public function getSep10JWTSigningKey(): string
    {
        return $this->sep10JwtSigningSeed;
    }
}
