<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep31InfoField
{
    public string $fieldName;
    public string $description;
    /**
     * @var array<string>|null
     */
    public ?array $choices = null;
    public ?bool $optional = null;

    /**
     * @param string $fieldName Name of the field.
     * @param string $description Description of field to show to user.
     * @param array<string>|null $choices (optional) An array of valid values for this field.
     * @param bool|null $optional if field is optional. Defaults to false
     */
    public function __construct(
        string $fieldName,
        string $description,
        ?array $choices = null,
        ?bool $optional = null,
    ) {
        $this->fieldName = $fieldName;
        $this->description = $description;
        $this->choices = $choices;
        $this->optional = $optional;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $data
         */
        $data = [
            'description' => $this->description,
        ];

        if ($this->choices !== null) {
            $data += ['choices' => $this->choices];
        }

        if ($this->optional !== null) {
            $data += ['optional' => $this->optional];
        }

        return [$this->fieldName => $data];
    }
}
