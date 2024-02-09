<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep24;

class FeeResponse
{
    public bool $enabled;
    public ?bool $authenticationRequired = null;

    public function __construct(bool $enabled, ?bool $authenticationRequired = null)
    {
        $this->enabled = $enabled;
        $this->authenticationRequired = $authenticationRequired;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $result
         */
        $result = ['enabled' => $this->enabled];

        if ($this->enabled && $this->authenticationRequired) {
            $result['authentication_required'] = true;
        }

        return $result;
    }
}
