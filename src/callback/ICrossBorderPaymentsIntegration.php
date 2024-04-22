<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\Sep31AssetInfo;

/**
 * The interface for the cross-border payments endpoint of the callback API.
 */
interface ICrossBorderPaymentsIntegration
{
    /**
     * Returns the supported assets.
     *
     * @return array<Sep31AssetInfo> The supported assets.
     */
    public function supportedAssets(string | null $lang): array;
}
