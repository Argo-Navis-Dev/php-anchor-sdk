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

    /**
     * Retrieves the localized text for the passed key.
     *
     * This method fetches the translation for the specified key in the given locale.
     * If the key does not exist or is empty, it will return the provided default value
     * or an empty string. Optional parameters can be passed to replace placeholders
     * in the localized text.
     *
     * @param string $key The key of the localized text.
     * @param string|null $locale The locale to be used.
     * @param string|null $default The default text if the key is not found.
     * @param array<string,string>|null $params The parameters to be used in the localized text.
     *
     * @return string The localized text.
     */
    public function getLocalizedText(
        string $key,
        ?string $locale = 'en',
        ?string $default = null,
        ?array $params = null,
    ): string;
}
