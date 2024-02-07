<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.
namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep12\Sep12Service;
use ArgoNavis\PhpAnchorSdk\shared\CustomerFieldType;
use ArgoNavis\PhpAnchorSdk\shared\CustomerStatus;
use ArgoNavis\PhpAnchorSdk\shared\ProvidedCustomerFieldStatus;
use ArgoNavis\Test\PhpAnchorSdk\callback\CustomerIntegration;
use ArgoNavis\Test\PhpAnchorSdk\util\ServerRequestBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soneso\StellarSDK\SEP\KYCService\GetCustomerInfoField;
use Soneso\StellarSDK\SEP\KYCService\GetCustomerInfoProvidedField;
use Soneso\StellarSDK\SEP\KYCService\GetCustomerInfoResponse;
use Soneso\StellarSDK\SEP\KYCService\PutCustomerInfoResponse;

use function PHPUnit\Framework\assertContains;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertIsString;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertTrue;
use function assert;
use function file_get_contents;
use function intval;
use function is_array;
use function is_string;
use function json_decode;
use function microtime;
use function strval;

class Sep12Test extends TestCase
{
    private string $customerEndpoint = 'https://test.com/sep12/customer';
    private string $customerVerificationEndpoint = 'https://test.com/sep12/customer/verification';
    private string $customerId = 'd1ce2f48-3ff1-495d-9240-7a50d806cfed';
    private string $accountId = 'GCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6H2M';
    private string $idFrontPath = 'tests/kyc/id_front.png';
    private string $idBackPath = 'tests/kyc/id_back.png';

    public function testGetCustomerSuccess(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->getCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());
        assertEquals(CustomerStatus::ACCEPTED, $responseData->getStatus());
        $providedFields = $responseData->getProvidedFields();
        assertNotNull($providedFields);
        assertCount(3, $providedFields);

        assertTrue(isset($providedFields['first_name']));
        $firstNameField = $providedFields['first_name'];
        assertInstanceOf(GetCustomerInfoProvidedField::class, $firstNameField);
        assertEquals(ProvidedCustomerFieldStatus::ACCEPTED, $firstNameField->getStatus());
        assertEquals("The customer's first name", $firstNameField->getDescription());
        assertEquals(CustomerFieldType::STRING, $firstNameField->getType());

        assertTrue(isset($providedFields['last_name']));
        $lastNameField = $providedFields['last_name'];
        assertInstanceOf(GetCustomerInfoProvidedField::class, $lastNameField);
        assertEquals(ProvidedCustomerFieldStatus::ACCEPTED, $lastNameField->getStatus());
        assertEquals("The customer's last name", $lastNameField->getDescription());
        assertEquals(CustomerFieldType::STRING, $lastNameField->getType());

        self::assertTrue(isset($providedFields['email_address']));
        $emailField = $providedFields['email_address'];
        assertInstanceOf(GetCustomerInfoProvidedField::class, $emailField);
        assertEquals(ProvidedCustomerFieldStatus::ACCEPTED, $emailField->getStatus());
        assertEquals("The customer's email address", $emailField->getDescription());
        assertEquals(CustomerFieldType::STRING, $emailField->getType());
    }

    public function testGetCustomerNeedsInfo(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId, 'memo' => '1'];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->getCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());
        assertEquals(CustomerStatus::NEEDS_INFO, $responseData->getStatus());
        $fields = $responseData->getFields();
        assertNotNull($fields);
        assertCount(2, $fields);

        self::assertTrue(isset($fields['mobile_number']));
        $mobileField = $fields['mobile_number'];
        assertInstanceOf(GetCustomerInfoField::class, $mobileField);
        assertEquals('phone number of the customer', $mobileField->getDescription());
        assertEquals(CustomerFieldType::STRING, $mobileField->getType());
        assertFalse($mobileField->isOptional());

        assertTrue(isset($fields['email_address']));
        $emailField = $fields['email_address'];
        assertInstanceOf(GetCustomerInfoField::class, $emailField);
        assertEquals('email address of the customer', $emailField->getDescription());
        assertEquals(CustomerFieldType::STRING, $emailField->getType());
        assertTrue($emailField->isOptional());

        $providedFields = $responseData->getProvidedFields();
        assertNotNull($providedFields);
        assertCount(2, $providedFields);

        assertTrue(isset($providedFields['first_name']));
        $firstNameField = $providedFields['first_name'];
        assertInstanceOf(GetCustomerInfoProvidedField::class, $firstNameField);
        assertEquals(ProvidedCustomerFieldStatus::ACCEPTED, $firstNameField->getStatus());
        assertEquals("The customer's first name", $firstNameField->getDescription());
        assertEquals(CustomerFieldType::STRING, $firstNameField->getType());

        assertTrue(isset($providedFields['last_name']));
        $lastNameField = $providedFields['last_name'];
        assertInstanceOf(GetCustomerInfoProvidedField::class, $lastNameField);
        assertEquals(ProvidedCustomerFieldStatus::ACCEPTED, $lastNameField->getStatus());
        assertEquals("The customer's last name", $lastNameField->getDescription());
        assertEquals(CustomerFieldType::STRING, $lastNameField->getType());
    }

    public function testGetCustomerUnknown(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId, 'memo' => '2'];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->getCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());
        assertEquals(CustomerStatus::NEEDS_INFO, $responseData->getStatus());
        $fields = $responseData->getFields();
        assertNotNull($fields);
        assertCount(3, $fields);

        assertTrue(isset($fields['email_address']));
        $emailField = $fields['email_address'];
        assertInstanceOf(GetCustomerInfoField::class, $emailField);
        assertEquals('Email address of the customer', $emailField->getDescription());
        assertEquals(CustomerFieldType::STRING, $emailField->getType());
        assertTrue($emailField->isOptional());

        assertTrue(isset($fields['id_type']));
        $idTypeField = $fields['id_type'];
        assertInstanceOf(GetCustomerInfoField::class, $idTypeField);
        assertEquals('Government issued ID', $idTypeField->getDescription());
        assertEquals(CustomerFieldType::STRING, $idTypeField->getType());
        assertFalse($idTypeField->isOptional());
        $choices = $idTypeField->getChoices();
        assertNotNull($choices);
        assertContains('Passport', $choices);
        assertContains('Drivers License', $choices);
        assertContains('State ID', $choices);

        assertTrue(isset($fields['photo_id_front']));
        $pIdField = $fields['photo_id_front'];
        assertInstanceOf(GetCustomerInfoField::class, $pIdField);
        assertEquals('A clear photo of the front of the government issued ID', $pIdField->getDescription());
        assertEquals(CustomerFieldType::BINARY, $pIdField->getType());
        assertFalse($pIdField->isOptional());
    }

    public function testGetCustomerProcessing(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId, 'memo' => '3'];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->getCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());
        assertEquals(CustomerStatus::PROCESSING, $responseData->getStatus());
        assertEquals(
            'Photo ID requires manual review. This process typically takes 1-2 business days.',
            $responseData->getMessage(),
        );

        $providedFields = $responseData->getProvidedFields();
        assertNotNull($providedFields);
        assertCount(1, $providedFields);

        assertTrue(isset($providedFields['photo_id_front']));
        $pIdField = $providedFields['photo_id_front'];
        assertInstanceOf(GetCustomerInfoProvidedField::class, $pIdField);
        assertEquals(ProvidedCustomerFieldStatus::PROCESSING, $pIdField->getStatus());
        assertEquals('A clear photo of the front of the government issued ID', $pIdField->getDescription());
        assertEquals(CustomerFieldType::BINARY, $pIdField->getType());
    }

    public function testGetCustomerRejected(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId, 'memo' => '4'];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->getCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());
        assertEquals(CustomerStatus::REJECTED, $responseData->getStatus());
        assertEquals(
            'This person is on a sanctions list',
            $responseData->getMessage(),
        );
    }

    public function testGetCustomerRequiresVerification(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId, 'memo' => '5'];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->getCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());
        assertEquals(CustomerStatus::NEEDS_INFO, $responseData->getStatus());

        $providedFields = $responseData->getProvidedFields();
        assertNotNull($providedFields);
        assertCount(1, $providedFields);

        assertTrue(isset($providedFields['mobile_number']));
        $mobileField = $providedFields['mobile_number'];
        assertInstanceOf(GetCustomerInfoProvidedField::class, $mobileField);
        assertEquals(ProvidedCustomerFieldStatus::VERIFICATION_REQUIRED, $mobileField->getStatus());
        assertEquals('phone number of the customer', $mobileField->getDescription());
        assertEquals(CustomerFieldType::STRING, $mobileField->getType());
    }

    public function testGetCustomerErrors(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        // lang must be a string
        $data = ['account' => $this->accountId, 'lang' => 12344];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'lang must be a string');

        // The account specified does not match authorization token
        $data = ['account' => 'GB6E3WGW6HJBZHUNR6Z5PBDNUERIQYJOKPTG2XG46O4AZUVPEA342UU5'];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'The account specified does not match authorization token');

        // The memo specified does not match the memo ID authorized via SEP-10
        $sep10JwtMemo = $this->createSep10Jwt($this->accountId . ':' . '1234');
        $data = ['account' => $this->accountId, 'memo' => '39393'];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10JwtMemo);
        $this->checkError($response, 400, 'The memo specified does not match the memo ID authorized via SEP-10');

        $sep10JwtMemo = $this->createSep10Jwt('MCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6AAAAAAAAAAE2LE36');
        $data = ['account' => 'MCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6AAAAAAAAAAE2LE36',
            'memo' => '39393',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10JwtMemo);
        $this->checkError($response, 400, 'The memo specified does not match the memo ID authorized via SEP-10');

        // 'Invalid memo ' . $memo . ' of type: id'
        $data = ['account' => $this->accountId, 'memo' => 'blub', 'memo_type' => 'id'];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Invalid memo blub of type: id');

        // 'Invalid memo ' . $memo . ' of type: text'
        $data = ['account' => $this->accountId,
            'memo' => 'this is a very long memo, having more than 28 characters',
            'memo_type' => 'text',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'Invalid memo this is a very long memo, having more than 28 characters of type: text',
        );

        // customer not found for id
        $data = ['id' => '7e285e7d-d984-412c-97bc-909d0e399fbf'];
        $request = ServerRequestBuilder::getServerRequest($this->customerEndpoint, $data);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 404, 'customer not found for id: 7e285e7d-d984-412c-97bc-909d0e399fbf');
    }

    public function testPutCustomerSuccess(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId];

        $request = $this->putServerRequest($data, $this->customerEndpoint, 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->putCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());

        $request = $this->putServerRequest($data, $this->customerEndpoint, 'multipart/form-data');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->putCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());

        $request = $this->putServerRequest($data, $this->customerEndpoint, 'application/x-www-form-urlencoded');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->putCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());

        $request = $this->putServerRequest($data, $this->customerEndpoint, 'multipart/form-data');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->putCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());

        $idFrontContent = file_get_contents($this->idFrontPath);
        $idBackContent = file_get_contents($this->idBackPath);
        assertIsString($idFrontContent);
        assertIsString($idBackContent);
        $idFront = ['filename' => 'id_front.png', 'contents' => $idFrontContent];
        $idBack = ['filename' => 'id_back.png', 'contents' => $idBackContent];
        $files = ['photo_id_front' => $idFront, 'photo_id_back' => $idBack];
        $request = $this->putServerRequest($data, $this->customerEndpoint, 'multipart/form-data', $files);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->putCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());
    }

    public function testPutCustomerErrors(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        // The account specified does not match authorization token
        $data = ['account' => 'GB6E3WGW6HJBZHUNR6Z5PBDNUERIQYJOKPTG2XG46O4AZUVPEA342UU5'];
        $request = $this->putServerRequest($data, $this->customerEndpoint, 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'The account specified does not match authorization token');

        // The memo specified does not match the memo ID authorized via SEP-10
        $sep10JwtMemo = $this->createSep10Jwt($this->accountId . ':' . '1234');
        $data = ['account' => $this->accountId, 'memo' => '39393'];
        $request = $this->putServerRequest($data, $this->customerEndpoint, 'multipart/form-data');
        $response = $sep12Service->handleRequest($request, $sep10JwtMemo);
        $this->checkError($response, 400, 'The memo specified does not match the memo ID authorized via SEP-10');

        $sep10JwtMemo = $this->createSep10Jwt('MCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6AAAAAAAAAAE2LE36');
        $data = ['account' => 'MCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6AAAAAAAAAAE2LE36',
            'memo' => '39393',
        ];
        $request = $this->putServerRequest($data, $this->customerEndpoint, 'application/x-www-form-urlencoded');
        $response = $sep12Service->handleRequest($request, $sep10JwtMemo);
        $this->checkError($response, 400, 'The memo specified does not match the memo ID authorized via SEP-10');

        // 'Invalid memo ' . $memo . ' of type: id'
        $data = ['account' => $this->accountId, 'memo' => 'blub', 'memo_type' => 'id'];
        $request = $this->putServerRequest($data, $this->customerEndpoint, 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Invalid memo blub of type: id');

        // 'Invalid memo ' . $memo . ' of type: text'
        $data = ['account' => $this->accountId,
            'memo' => 'this is a very long memo, having more than 28 characters',
            'memo_type' => 'text',
        ];
        $request = $this->putServerRequest($data, $this->customerEndpoint, 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'Invalid memo this is a very long memo, having more than 28 characters of type: text',
        );

        // customer not found for id
        $data = ['id' => '7e285e7d-d984-412c-97bc-909d0e399fbf'];
        $request = $this->putServerRequest($data, $this->customerEndpoint, 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 404, 'customer not found for id: 7e285e7d-d984-412c-97bc-909d0e399fbf');
    }

    public function testPutCustomerVerificationSuccess(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['id' => $this->customerId, 'mobile_number_verification' => '2735021'];
        $request = $this->putServerRequest($data, $this->customerVerificationEndpoint, 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $responseData = $this->getCustomerInfo($response);
        assertEquals($this->customerId, $responseData->getId());
    }

    public function testPutCustomerVerificationErrors(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        // missing id
        $data = ['mobile_number_verification' => '2735021'];
        $request = $this->putServerRequest($data, $this->customerVerificationEndpoint, 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing id');

        // invalid key
        $data = ['id' => $this->customerId, 'mobile_number' => '2735021'];
        $request = $this->putServerRequest(
            $data,
            $this->customerVerificationEndpoint,
            'application/x-www-form-urlencoded',
        );
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid key mobile_number');

        // customer not found for id
        $data = ['id' => '7e285e7d-d984-412c-97bc-909d0e399fbf', 'mobile_number_verification' => '2735021'];
        $request = $this->putServerRequest($data, $this->customerVerificationEndpoint, 'multipart/form-data');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 404, 'customer not found for id: 7e285e7d-d984-412c-97bc-909d0e399fbf');
    }

    public function testDeleteCustomerSuccess(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['memo' => '100'];
        $request = $this->deleteServerRequest($data, $this->accountId, 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        self::assertEquals(200, $response->getStatusCode());

        $request = $this->deleteServerRequest($data, $this->accountId, 'application/x-www-form-urlencoded');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        self::assertEquals(200, $response->getStatusCode());

        $request = $this->deleteServerRequest($data, $this->accountId, 'multipart/form-data');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteCustomerErrors(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = [];
        $request = $this->deleteServerRequest(
            $data,
            accountId: 'GB6E3WGW6HJBZHUNR6Z5PBDNUERIQYJOKPTG2XG46O4AZUVPEA342UU5',
            contentType: 'application/json',
        );
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 401, 'Not authorized to delete account.');

        $sep10JwtMemo = $this->createSep10Jwt($this->accountId . ':' . '1234');
        $data = ['memo' => '39393'];
        $request = $this->deleteServerRequest($data, $this->accountId, 'application/x-www-form-urlencoded');
        $response = $sep12Service->handleRequest($request, $sep10JwtMemo);
        $this->checkError($response, 401, 'Not authorized to delete account.');

        $data = ['memo' => '1234'];
        $request = $this->deleteServerRequest($data, $this->accountId, 'multipart/form-data');
        $response = $sep12Service->handleRequest($request, $sep10JwtMemo);
        self::assertEquals(200, $response->getStatusCode());
    }

    private function createSep10Jwt(string $sub): Sep10Jwt
    {
        $iss = 'https://test.com/auth';
        $jti = 'test';
        $currentTime = intval(microtime(true));
        $iat = strval($currentTime);
        $exp = strval(($currentTime + 5 * 60));

        return new Sep10Jwt($iss, $sub, $iat, $exp, $jti);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param ?array<string, array<string, mixed>> $files (field_name => [file_name => ... , content => ...]
     */
    private function putServerRequest(
        array $parameters,
        string $uri,
        string $contentType,
        ?array $files = null,
    ): ServerRequestInterface {
        return ServerRequestBuilder::serverRequest('PUT', $parameters, $uri, $contentType, $files);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function deleteServerRequest(
        array $parameters,
        string $accountId,
        string $contentType,
    ): ServerRequestInterface {
        return ServerRequestBuilder::serverRequest(
            'DELETE',
            $parameters,
            $this->customerEndpoint . '/' . $accountId,
            $contentType,
        );
    }

    private function getCustomerInfo(ResponseInterface $response): GetCustomerInfoResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return GetCustomerInfoResponse::fromJson($decoded);
    }

    private function putCustomerInfo(ResponseInterface $response): PutCustomerInfoResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return PutCustomerInfoResponse::fromJson($decoded);
    }

    private function checkError(ResponseInterface $response, int $statusCode, string $message): void
    {
        assertEquals($statusCode, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));
        assertTrue(is_string($decoded['error']));
        $errorMsg = $decoded['error'];
        assertEquals($message, $errorMsg);
    }
}
