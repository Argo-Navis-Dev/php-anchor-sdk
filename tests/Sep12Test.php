<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.
namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep12\Sep12Service;
use ArgoNavis\Test\PhpAnchorSdk\callback\CustomerIntegration;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;

use function intval;
use function json_encode;
use function microtime;
use function strval;

class Sep12Test extends TestCase
{
    private string $customerEndpoint = 'https://test.com/sep12/customer';
    private string $customerVerificationEndpoint = 'https://test.com/sep12/customer/verification';
    private string $customerId = 'd1ce2f48-3ff1-495d-9240-7a50d806cfed';
    private string $accountId = 'GCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6H2M';

    public function testGetCustomer(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId];
        $request = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri($this->customerEndpoint))
            ->withQueryParams($data)
            ->withAddedHeader('Content-Type', 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testPutCustomer(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId];
        $request = (new ServerRequest())
            ->withMethod('PUT')
            ->withUri(new Uri($this->customerEndpoint))
            ->withBody($this->getStreamFromDataArray($data))
            ->withAddedHeader('Content-Type', 'application/json');
        $encoded = json_encode($data);
        self::assertIsString($encoded);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testPutCustomerVerification(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['id' => $this->customerId, 'mobile_number_verification' => '2735021'];
        $request = (new ServerRequest())
            ->withMethod('PUT')
            ->withUri(new Uri($this->customerVerificationEndpoint))
            ->withBody($this->getStreamFromDataArray($data))
            ->withAddedHeader('Content-Type', 'application/json');
        $encoded = json_encode($data);
        self::assertIsString($encoded);
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteCustomer(): void
    {
        $customerIntegration = new CustomerIntegration();
        $sep12Service = new Sep12Service($customerIntegration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        $data = ['account' => $this->accountId];
        $request = (new ServerRequest())
            ->withMethod('DELETE')
            ->withUri(new Uri($this->customerEndpoint . '/' . $this->accountId))
            ->withQueryParams($data)
            ->withAddedHeader('Content-Type', 'application/json');
        $response = $sep12Service->handleRequest($request, $sep10Jwt);
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
}
