<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\config;

use Soneso\StellarSDK\Network;

interface IAppConfig
{
    /**
     * @return Network the stellar network to be used.
     */
    public function getStellarNetwork(): Network;

    /**
     * @return string The horizon url to be used.
     */
    public function getHorizonUrl(): string;
}
