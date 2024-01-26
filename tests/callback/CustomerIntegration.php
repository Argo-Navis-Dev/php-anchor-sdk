<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\callback\GetCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\GetCustomerResponse;
use ArgoNavis\PhpAnchorSdk\callback\ICustomerIntegration;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerResponse;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerVerificationRequest;
use ArgoNavis\PhpAnchorSdk\shared\CustomerFieldType;
use ArgoNavis\PhpAnchorSdk\shared\CustomerStatus;
use ArgoNavis\PhpAnchorSdk\shared\ProvidedCustomerFields;

class CustomerIntegration implements ICustomerIntegration
{
    private string $id = 'd1ce2f48-3ff1-495d-9240-7a50d806cfed';

    public function getCustomer(GetCustomerRequest $request): GetCustomerResponse
    {
        $firstName = new ProvidedCustomerFields(
            fieldName: 'first_name',
            type: CustomerFieldType::STRING,
            description: "The customer's first name",
            status: CustomerStatus::ACCEPTED,
        );

        $lastName = new ProvidedCustomerFields(
            fieldName: 'last_name',
            type: CustomerFieldType::STRING,
            description: "The customer's last name",
            status: CustomerStatus::ACCEPTED,
        );

        $emailAddress = new ProvidedCustomerFields(
            fieldName: 'email_address',
            type: CustomerFieldType::STRING,
            description: "The customer's email address",
            status: CustomerStatus::ACCEPTED,
        );

        $providedFields = [$firstName, $lastName, $emailAddress];

        return new GetCustomerResponse(
            status: CustomerStatus::ACCEPTED,
            id: $this->id,
            providedFields: $providedFields,
        );
    }

    public function putCustomer(PutCustomerRequest $request): PutCustomerResponse
    {
        return new PutCustomerResponse(id: $this->id);
    }

    public function putCustomerVerification(PutCustomerVerificationRequest $request): GetCustomerResponse
    {
        return $this->getCustomer(new GetCustomerRequest($this->id));
    }

    public function deleteCustomer(string $id): void
    {
    }
}
