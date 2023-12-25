<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\config;

use ArgoNavis\PhpAnchorSdk\config\IAppConfig;
use Soneso\StellarSDK\Network;

class AppConfig implements IAppConfig
{
    public function getStellarNetwork(): Network
    {
        return Network::testnet();
    }

    public function getHorizonUrl(): string
    {
        return 'https://horizon-testnet.stellar.org';
    }
}
