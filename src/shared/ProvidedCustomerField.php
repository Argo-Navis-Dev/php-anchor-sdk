<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

/**
 * The provided fields object defines the pieces of information the anchor has received for the customer.
 * It is not required unless one or more of provided fields require verification via PUT /customer/verification.
 *
 * see <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#provided-fields">Provided Fields</a>
 */
class ProvidedCustomerField
{
    public string $fieldName;
    public string $type;
    public string $description;
    /**
     * @var array<string>|null
     */
    public ?array $choices = null;
    public ?bool $optional = false;

    public ?string $status = null;
    public ?string $error = null;

    /**
     * @param string $fieldName Name of the field. Must be a field name defined in <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>
     * @param string $type The data type of the field value. Can be string, binary, number, or date
     * @param string $description A human-readable description of this field, especially important if this is not a SEP-9 field.
     * @param string[]|null $choices (optional) An array of valid values for this field.
     * @param bool|null $optional (optional) A boolean whether this field is required to proceed or not. Defaults to false.
     * @param string|null $status (optional) One of the values described in
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#provided-field-statuses">Provided Field Statuses</a>.
     * If the server does not wish to expose which field(s) were accepted or rejected, this property can be omitted.
     * @param string|null $error (optional) The human-readable description of why the field is REJECTED.
     */
    public function __construct(
        string $fieldName,
        string $type,
        string $description,
        ?array $choices = null,
        ?bool $optional = false,
        ?string $status = null,
        ?string $error = null,
    ) {
        $this->fieldName = $fieldName;
        $this->type = $type;
        $this->description = $description;
        $this->choices = $choices;
        $this->optional = $optional;
        $this->status = $status;
        $this->error = $error;
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

        if ($this->status !== null) {
            $data += ['status' => $this->status];
        }

        if ($this->error !== null) {
            $data += ['error' => $this->error];
        }

        return [$this->fieldName => $data];
    }
}
