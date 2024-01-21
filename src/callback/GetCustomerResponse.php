<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\CustomerField;
use ArgoNavis\PhpAnchorSdk\shared\ProvidedCustomerFields;

/**
 * The response body of the GET /customer endpoint.
 *
 *  See: <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#customer-get">Customer GET</a>.
 */
class GetCustomerResponse
{
    /**
     * @var string|null (optional) ID of the customer, if the customer has already been created via a PUT /customer request.
     */
    public ?string $id;

    /**
     * @var string (optional) ID of the customer, if the customer has already been created via a PUT /customer request.
     */
    public string $status;

    /**
     * @var array<CustomerField>|null (optional) An array containing the fields the anchor
     *  has not yet received for the given customer of the type provided in the request.
     *  Required for customers in the NEEDS_INFO status.
     */
    public ?array $fields = null;
    /**
     * @var array<ProvidedCustomerFields>|null (optional) An array containing the fields the anchor has received for the given customer.
     *  See <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#provided-fields">Provided Fields</a> for more detailed information.
     *  Required for customers whose information needs verification via PUT /customer/verification.
     */
    public ?array $providedFields = null;

    /**
     * @param string $status Status of the customers KYC process.
     * @param string|null $id (optional) ID of the customer, if the customer has already been created via a PUT /customer request.
     * @param array<CustomerField>|null $fields (optional) An array containing the fields the anchor
     * has not yet received for the given customer of the type provided in the request.
     * Required for customers in the NEEDS_INFO status.
     * See <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#fields">Fields</a> for more detailed information.
     * @param array<ProvidedCustomerFields>|null $providedFields (optional) An array containing the fields the anchor has received for the given customer.
     * See <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#provided-fields">Provided Fields</a> for more detailed information.
     * Required for customers whose information needs verification via PUT /customer/verification.
     */
    public function __construct(
        string $status,
        ?string $id = null,
        ?array $fields = null,
        ?array $providedFields = null,
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->fields = $fields;
        $this->providedFields = $providedFields;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $json
         */
        $json = ['status' => $this->status];

        if ($this->id !== null) {
            $json += ['id' => $this->id];
        }

        if ($this->fields !== null) {
            /*
             * @var array<string, mixed> $fieldsData
             */
            $fieldsData = [];
            foreach ($this->fields as $field) {
                $fieldsData += $field->toJson();
            }
            $json += ['fields' => $fieldsData];
        }

        if ($this->providedFields !== null) {
            /*
             * @var array<string, mixed> $fieldsData
             */
            $fieldsData = [];
            foreach ($this->providedFields as $field) {
                $fieldsData += $field->toJson();
            }
            $json += ['provided_fields' => $fieldsData];
        }

        return $json;
    }
}
