<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

/**
 * The fields object defines the pieces of information the anchor has not yet received for the customer.
 *
 * It is required for the NEEDS_INFO status but may be included with any status. Fields should be specified as an object with keys representing the SEP-9 field names required.
 *
 * Customers in the ACCEPTED status should not have any required fields present in the object, since all required fields should have already been provided.
 *
 * see <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#fields">Fields</a>
 */
class CustomerField
{
    public string $fieldName;
    public string $type;
    public string $description;
    /**
     * @var array<string>|null
     */
    public ?array $choices = null;
    public ?bool $optional = false;

    /**
     * @param string $fieldName Name of the field. Must be a field name defined in <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>
     * @param string $type The data type of the field value. Can be string, binary, number, or date.
     * @param string $description A human-readable description of this field, especially important if this is not a SEP-9 field.
     * @param string[]|null $choices (optional) An array of valid values for this field.
     * @param bool|null $optional (optional) A boolean whether this field is required to proceed or not. Defaults to false.
     */
    public function __construct(
        string $fieldName,
        string $type,
        string $description,
        ?array $choices = null,
        ?bool $optional = false,
    ) {
        $this->fieldName = $fieldName;
        $this->type = $type;
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
            'type' => $this->type,
            'description' => $this->description,
        ];

        if ($this->choices !== null) {
            $data += ['choices' => $this->choices];
        }

        if ($this->optional) {
            $data += ['optional' => $this->optional];
        }

        return [$this->fieldName => $data];
    }
}
