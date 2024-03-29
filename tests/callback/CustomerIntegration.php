<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\callback\GetCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\GetCustomerResponse;
use ArgoNavis\PhpAnchorSdk\callback\ICustomerIntegration;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerCallbackRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerResponse;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerVerificationRequest;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\CustomerNotFoundForId;
use ArgoNavis\PhpAnchorSdk\shared\CustomerField;
use ArgoNavis\PhpAnchorSdk\shared\CustomerFieldType;
use ArgoNavis\PhpAnchorSdk\shared\CustomerStatus;
use ArgoNavis\PhpAnchorSdk\shared\ProvidedCustomerField;
use ArgoNavis\PhpAnchorSdk\shared\ProvidedCustomerFieldStatus;

use function PHPUnit\Framework\assertIsString;

class CustomerIntegration implements ICustomerIntegration
{
    private string $id = 'd1ce2f48-3ff1-495d-9240-7a50d806cfed';

    public function getCustomer(GetCustomerRequest $request): GetCustomerResponse
    {
        if ($request->id !== null && $request->id !== $this->id) {
            throw new CustomerNotFoundForId($request->id);
        }

        if ($request->account === 'GCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6H2M') {
            return $this->getCustomerNeedsInfo();
        }

        if ($request->account === 'GCCEXZJSFH4X2L5OJWXPVV3JGFCEYWYKVIV4326L6S45KXLG6PFP5RMC') {
            return $this->getCustomerUnknown();
        }

        if ($request->account === 'GCV3PQRO2BZVFZ47V7XTSPNVETFKFRTTOFAVSYMIN7GGBIV276BBCU7M') {
            return $this->getCustomerProcessing();
        }

        if ($request->account === 'GCBQLFEJBH7YFASYYTRJKKT2GOEZR5EMPJWIK2CEG3JY46OC2NK4IG3Q') {
            return $this->getCustomerRejected();
        }

        if ($request->account === 'GDA7IDTVMELUBL6VMMKVCOUYZDTCBND26ZNRAFDBSXSK5LDOTHXFOYYK') {
            return $this->getCustomerVerificationRequired();
        }

        return $this->getCustomerSuccess();
    }

    public function putCustomer(PutCustomerRequest $request): PutCustomerResponse
    {
        if ($request->id !== null && $request->id !== $this->id) {
            throw new CustomerNotFoundForId($request->id);
        }

        if ($request->kycUploadedFiles !== null) {
            foreach ($request->kycUploadedFiles as $file) {
                $fileName = $file->getClientFilename();
                assertIsString($fileName);
                $file->moveTo('tests/kyc/down_' . $fileName);
            }
        }

        return new PutCustomerResponse(id: $this->id);
    }

    public function putCustomerVerification(PutCustomerVerificationRequest $request): GetCustomerResponse
    {
        if ($request->id !== $this->id) {
            throw new CustomerNotFoundForId($request->id);
        }

        return $this->getCustomer(new GetCustomerRequest($request->account, $request->memo, $this->id));
    }

    public function deleteCustomer(string $id): void
    {
        if ($id !== $this->id) {
            throw new CustomerNotFoundForId($id);
        }
    }

    public function putCustomerCallback(PutCustomerCallbackRequest $request): void
    {
        if ($request->url !== 'https://test.com/cu') {
            throw new AnchorFailure('test');
        }

        if ($request->id !== null && $request->id !== $this->id) {
            throw new CustomerNotFoundForId($request->id);
        }
    }

    // The case when a customer has been successfully KYC'd and approved
    private function getCustomerSuccess(): GetCustomerResponse
    {
        $firstName = new ProvidedCustomerField(
            fieldName: 'first_name',
            type: CustomerFieldType::STRING,
            description: "The customer's first name",
            status: ProvidedCustomerFieldStatus::ACCEPTED,
        );

        $lastName = new ProvidedCustomerField(
            fieldName: 'last_name',
            type: CustomerFieldType::STRING,
            description: "The customer's last name",
            status: ProvidedCustomerFieldStatus::ACCEPTED,
        );

        $emailAddress = new ProvidedCustomerField(
            fieldName: 'email_address',
            type: CustomerFieldType::STRING,
            description: "The customer's email address",
            status: ProvidedCustomerFieldStatus::ACCEPTED,
        );

        $providedFields = [$firstName, $lastName, $emailAddress];

        return new GetCustomerResponse(
            status: CustomerStatus::ACCEPTED,
            id: $this->id,
            providedFields: $providedFields,
        );
    }

    // The case when a customer has provided some but not all required information.
    private function getCustomerNeedsInfo(): GetCustomerResponse
    {
        $mobileNumber = new CustomerField(
            fieldName: 'mobile_number',
            type: CustomerFieldType::STRING,
            description: 'phone number of the customer',
        );

        $emailAddress = new CustomerField(
            fieldName: 'email_address',
            type: CustomerFieldType::STRING,
            description: 'email address of the customer',
            optional: true,
        );

        $firstName = new ProvidedCustomerField(
            fieldName: 'first_name',
            type: CustomerFieldType::STRING,
            description: "The customer's first name",
            status: ProvidedCustomerFieldStatus::ACCEPTED,
        );

        $lastName = new ProvidedCustomerField(
            fieldName: 'last_name',
            type: CustomerFieldType::STRING,
            description: "The customer's last name",
            status: ProvidedCustomerFieldStatus::ACCEPTED,
        );

        $neededFields = [$mobileNumber, $emailAddress];
        $providedFields = [$firstName, $lastName];

        return new GetCustomerResponse(
            status: CustomerStatus::NEEDS_INFO,
            id: $this->id,
            fields: $neededFields,
            providedFields: $providedFields,
        );
    }

    // The case when an anchor requires info about an unknown customer
    private function getCustomerUnknown(): GetCustomerResponse
    {
        $emailAddress = new CustomerField(
            fieldName: 'email_address',
            type: CustomerFieldType::STRING,
            description: 'Email address of the customer',
            optional: true,
        );

        $idType = new CustomerField(
            fieldName: 'id_type',
            type: CustomerFieldType::STRING,
            description: 'Government issued ID',
            choices: ['Passport', 'Drivers License', 'State ID'],
        );

        $photoIdFront = new CustomerField(
            fieldName: 'photo_id_front',
            type: CustomerFieldType::BINARY,
            description: 'A clear photo of the front of the government issued ID',
        );

        $neededFields = [$emailAddress, $idType, $photoIdFront];

        return new GetCustomerResponse(
            status: CustomerStatus::NEEDS_INFO,
            id: $this->id,
            fields: $neededFields,
        );
    }

    private function getCustomerProcessing(): GetCustomerResponse
    {
        $photoIdFront = new ProvidedCustomerField(
            fieldName: 'photo_id_front',
            type: CustomerFieldType::BINARY,
            description: 'A clear photo of the front of the government issued ID',
            status: ProvidedCustomerFieldStatus::PROCESSING,
        );

        return new GetCustomerResponse(
            status: CustomerStatus::PROCESSING,
            id: $this->id,
            message: 'Photo ID requires manual review. This process typically takes 1-2 business days.',
            providedFields: [$photoIdFront],
        );
    }

    private function getCustomerRejected(): GetCustomerResponse
    {
        return new GetCustomerResponse(
            status: CustomerStatus::REJECTED,
            id: $this->id,
            message: 'This person is on a sanctions list',
        );
    }

    private function getCustomerVerificationRequired(): GetCustomerResponse
    {
        $mobileNumber = new ProvidedCustomerField(
            fieldName: 'mobile_number',
            type: CustomerFieldType::STRING,
            description: 'phone number of the customer',
            status: ProvidedCustomerFieldStatus::VERIFICATION_REQUIRED,
        );

        return new GetCustomerResponse(
            status: CustomerStatus::NEEDS_INFO,
            id: $this->id,
            providedFields: [$mobileNumber],
        );
    }
}
