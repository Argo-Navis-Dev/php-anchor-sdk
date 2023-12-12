<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep10;

class ClientDomainData
{
    public string $clientDomain;
    public string $clientDomainAccountId;

    public function __construct(string $clientDomain, string $clientDomainAccountId)
    {
        $this->clientDomain = $clientDomain;
        $this->clientDomainAccountId = $clientDomainAccountId;
    }
}
