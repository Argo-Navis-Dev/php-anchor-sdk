<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep24;

class FeeResponse
{
    public bool $enabled;

    public function __construct(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'enabled' => $this->enabled,
        ];
    }
}
