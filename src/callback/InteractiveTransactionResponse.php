<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

class InteractiveTransactionResponse
{
    public string $type;
    public string $url;
    public string $id;

    public function __construct(string $type, string $url, string $id)
    {
        $this->type = $type;
        $this->url = $url;
        $this->id = $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'type' => $this->type,
            'url' => $this->url,
            'id' => $this->id,
        ];
    }
}
