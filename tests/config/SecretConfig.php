<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\config;

use ArgoNavis\PhpAnchorSdk\config\ISecretConfig;

class SecretConfig implements ISecretConfig
{
    private string $sep10Seed = 'SCYJJBZTHTN2RZI7UA2MN3RNMSDNQ3BKHPYWXXPXMRJ4KLU7N5XQ5BXE'; // GA4A5CVA2QJNS5CBPOEFKWJC4F5SUI36IPWHAKIEKBQ7UVGJ4Y5WC5FA
    public function getSep10SigningSeed(): ?string
    {
        return $this->sep10Seed;
    }
}
