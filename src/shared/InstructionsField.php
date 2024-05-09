<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

/**
 * Instructions fields are used in SEP-06. They describe how to complete the off-chain deposit.
 */
class InstructionsField
{
    /**
     * @var string $name The of the field. Should be a [SEP-9 financial account fields](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md#financial-account-fields)
     * name. E.g. 'organization.bank_account_number'
     */
    public string $name;
    /**
     * @var string $value The value of the field. E.g. '13719713158835300' (for bank_account_number)
     */
    public string $value;
    /**
     * @var string $description A human-readable description of the field. E.g. 'US bank account number'
     *  This can also be used to provide any additional information about a field that is not defined in
     *  the SEP-9 standard.
     */
    public string $description;

    /**
     * @param string $name The name of the field. Should be a [SEP-9 financial account fields](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md#financial-account-fields)
     *  name. E.g. 'organization.bank_account_number'
     * @param string $value The value of the field. E.g. '13719713158835300' (for bank_account_number)
     * @param string $description A human-readable description of the field. E.g. 'US bank account number'
     * This can also be used to provide any additional information about a field that is not defined in
     * the SEP-9 standard.
     */
    public function __construct(string $name, string $value, string $description)
    {
        $this->name = $name;
        $this->value = $value;
        $this->description = $description;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'description' => $this->description,
        ];
    }
}
