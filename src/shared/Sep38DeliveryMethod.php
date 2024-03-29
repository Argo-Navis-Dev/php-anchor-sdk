<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep38DeliveryMethod
{
    /**
     * @var string $name The value to use when making POST /quote requests.
     */
    public string $name;

    /**
     * @var string $description A human-readable description of the method identified by name.
     */
    public string $description;

    /**
     * Constructor.
     *
     * @param string $name The value to use when making POST /quote requests.
     * @param string $description A human-readable description of the method identified by name.
     */
    public function __construct(string $name, string $description)
    {
        $this->name = $name;
        $this->description = $description;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
