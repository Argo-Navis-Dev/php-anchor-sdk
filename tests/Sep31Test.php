<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep31\Sep31Service;
use ArgoNavis\PhpAnchorSdk\shared\Sep31TransactionStatus;
use ArgoNavis\Test\PhpAnchorSdk\callback\CrossBorderIntegration;
use ArgoNavis\Test\PhpAnchorSdk\callback\QuotesIntegration;
use ArgoNavis\Test\PhpAnchorSdk\util\ServerRequestBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\SEP\CrossBorderPayments\SEP31InfoResponse;
use Soneso\StellarSDK\SEP\CrossBorderPayments\SEP31PostTransactionsResponse;
use Soneso\StellarSDK\SEP\CrossBorderPayments\SEP31TransactionResponse;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertTrue;
use function assert;
use function count;
use function error_reporting;
use function intval;
use function is_array;
use function is_string;
use function json_decode;
use function microtime;
use function strval;

use const E_ALL;

class Sep31Test extends TestCase
{
    private string $infoEndpoint = 'https://test.com/sep31/info';
    private string $transactionsEndpoint = 'https://test.com/sep31/transactions';

    private string $accountId;

    public function setUp(): void
    {
        // Turn on error reporting
        error_reporting(E_ALL);
        $this->accountId = KeyPair::random()->getAccountId();
    }

    public function testGetInfo(): void
    {
        $integration = new CrossBorderIntegration();
        $sep31Service = new Sep31Service($integration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $request = ServerRequestBuilder::getServerRequest($this->infoEndpoint, ['lang' => 'en']);
        $response = $sep31Service->handleRequest($request, $sep10Jwt);

        $info = $this->getSep31InfoResponse($response);
        self::assertEquals(2, count($info->receiveAssets));

        $usdcAsset = $info->receiveAssets['USDC'];
        assertTrue($usdcAsset->quotesSupported);
        assertFalse($usdcAsset->quotesRequired);
        assertEquals(5, $usdcAsset->feeFixed);
        assertEquals(1, $usdcAsset->feePercent);
        assertEquals(0.1, $usdcAsset->minAmount);
        assertEquals(1000, $usdcAsset->maxAmount);
        $sep12 = $usdcAsset->sep12Info;
        $sep12SenderTypes = $sep12->senderTypes;
        assertCount(3, $sep12SenderTypes);
        assertArrayHasKey('sep31-sender', $sep12SenderTypes);
        assertEquals(
            'U.S. citizens limited to sending payments of less than $10,000 in value',
            $sep12SenderTypes['sep31-sender'],
        );
        assertArrayHasKey('sep31-large-sender', $sep12SenderTypes);
        assertEquals(
            'U.S. citizens that do not have sending limits',
            $sep12SenderTypes['sep31-large-sender'],
        );
        assertArrayHasKey('sep31-foreign-sender', $sep12SenderTypes);
        assertEquals(
            'non-U.S. citizens sending payments of less than $10,000 in value',
            $sep12SenderTypes['sep31-foreign-sender'],
        );
        $sep12ReceiverTypes = $sep12->receiverTypes;
        assertCount(2, $sep12ReceiverTypes);
        assertArrayHasKey('sep31-receiver', $sep12ReceiverTypes);
        assertEquals(
            'U.S. citizens receiving USD',
            $sep12ReceiverTypes['sep31-receiver'],
        );
        assertArrayHasKey('sep31-foreign-receiver', $sep12ReceiverTypes);
        assertEquals(
            'non-U.S. citizens receiving USD',
            $sep12ReceiverTypes['sep31-foreign-receiver'],
        );

        $ethAsset = $info->receiveAssets['ETH'];
        assertTrue($ethAsset->quotesSupported);
        assertFalse($ethAsset->quotesRequired);
        assertEquals(5, $ethAsset->feeFixed);
        assertEquals(1, $ethAsset->feePercent);
        assertEquals(0.1, $ethAsset->minAmount);
        assertEquals(1000, $ethAsset->maxAmount);
        $sep12 = $ethAsset->sep12Info;
        $sep12SenderTypes = $sep12->senderTypes;
        assertCount(3, $sep12SenderTypes);
        assertArrayHasKey('sep31-sender', $sep12SenderTypes);
        assertEquals(
            'U.S. citizens limited to sending payments of less than $10,000 in value',
            $sep12SenderTypes['sep31-sender'],
        );
        assertArrayHasKey('sep31-large-sender', $sep12SenderTypes);
        assertEquals(
            'U.S. citizens that do not have sending limits',
            $sep12SenderTypes['sep31-large-sender'],
        );
        assertArrayHasKey('sep31-foreign-sender', $sep12SenderTypes);
        assertEquals(
            'non-U.S. citizens sending payments of less than $10,000 in value',
            $sep12SenderTypes['sep31-foreign-sender'],
        );
        $sep12ReceiverTypes = $sep12->receiverTypes;
        assertCount(2, $sep12ReceiverTypes);
        assertArrayHasKey('sep31-receiver', $sep12ReceiverTypes);
        assertEquals(
            'U.S. citizens receiving USD',
            $sep12ReceiverTypes['sep31-receiver'],
        );
        assertArrayHasKey('sep31-foreign-receiver', $sep12ReceiverTypes);
        assertEquals(
            'non-U.S. citizens receiving USD',
            $sep12ReceiverTypes['sep31-foreign-receiver'],
        );
    }

    public function testPostTransaction(): void
    {
        $integration = new CrossBorderIntegration();
        $sep31Service = new Sep31Service($integration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $requestData = [
            'asset_code' => 'USDC',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'amount is required');

        $requestData = [
            'amount' => -10,
            'asset_code' => 'USDC',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'amount must be greater than zero');

        $requestData = [
            'amount' => 0.001,
            'asset_code' => 'USDC',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid amount 0.001 for asset USDC');

        $requestData = [
            'amount' => 100000000.001,
            'asset_code' => 'USDC',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid amount 100000000.001 for asset USDC');

        $requestData = [
            'amount' => 10,
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'asset_code is required');

        $requestData = [
            'amount' => 10,
            'asset_code' => 'DONUT',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'asset is not supported');

        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'asset_issuer' => 'GA5O3CCOMBAJBYSDBCXVXSHLX4DA4S5OQJ36DOKWVVMGHVVLKQS5X7KW',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'asset is not supported');

        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'asset_issuer' => 'GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN',
            'destination_asset' => 'iso4217:BRL',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Destination asset not supported. Can not find price.');

        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'asset_issuer' => 'GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN',
            'quote_id' => '9bff23f0-d1ff-442a-b366-3143cbc28bf5',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'quote_id not supported. Can not find quote.');

        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'asset_issuer' => 'GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'sender_id is required');

        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'sender_id' => '9bff0aee-4290-402a-9003-7abd8ae85ac1',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'receiver_id is required');

        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'sender_id' => '9bff0aee-4290-402a-9003-7abd8ae85ac1',
            'receiver_id' => '9bff0aee-4f9b-4f55-a41f-7deb48295924',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        assertEquals(201, $response->getStatusCode());
        $postResponse = $this->getPostTransactionResponse($response);
        assertEquals('9bff0aff-e8fb-47a7-81bb-0b776501cbb6', $postResponse->id);
        assertEquals('GDV2NKAAB5KXKGB7L65HOQ5XEGLXRTUGQET5JH2CNWSUIAHQH3FH7AN3', $postResponse->stellarAccountId);
        assertEquals('id', $postResponse->stellarMemoType);
        assertEquals('120190893', $postResponse->stellarMemo);

        // test destination asset
        $quotesIntegration = new QuotesIntegration();
        $sep31Service = new Sep31Service($integration, $quotesIntegration);
        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'destination_asset' => 'iso4217:RON',
            'sender_id' => '9bff0aee-4290-402a-9003-7abd8ae85ac1',
            'receiver_id' => '9bff0aee-4f9b-4f55-a41f-7deb48295924',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid operation for asset iso4217:RON');

        $sep31Service = new Sep31Service($integration, $quotesIntegration);
        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'destination_asset' => 'iso4217:BRL',
            'sender_id' => '9bff0aee-4290-402a-9003-7abd8ae85ac1',
            'receiver_id' => '9bff0aee-4f9b-4f55-a41f-7deb48295924',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        assertEquals(201, $response->getStatusCode());

        // test quote
        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'quote_id' => '9bff0aee-4290-402a-9003-7abd8ae85ac1',
            'sender_id' => '9bff0aee-4290-402a-9003-7abd8ae85ac1',
            'receiver_id' => '9bff0aee-4f9b-4f55-a41f-7deb48295924',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'quote not found for id: 9bff0aee-4290-402a-9003-7abd8ae85ac1');

        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'quote_id' => 'de762cda-a193-4961-861e-57b31fed6eb3',
            'sender_id' => '9bff0aee-4290-402a-9003-7abd8ae85ac1',
            'receiver_id' => '9bff0aee-4f9b-4f55-a41f-7deb48295924',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'quote sell asset does not match source_asset ' .
            'stellar:USDC:GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN',
        );

        $requestData = [
            'amount' => 10,
            'asset_code' => 'USDC',
            'quote_id' => 'sep6test-a193-4961-861e-57b31fed6eb3',
            'destination_asset' => 'iso4217:BRL',
            'sender_id' => '9bff0aee-4290-402a-9003-7abd8ae85ac1',
            'receiver_id' => '9bff0aee-4f9b-4f55-a41f-7deb48295924',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'quote amount does not match request amount');

        $requestData = [
            'amount' => 542,
            'asset_code' => 'USDC',
            'quote_id' => 'sep6test-a193-4961-861e-57b31fed6eb3',
            'destination_asset' => 'iso4217:BRL',
            'sender_id' => '9bff0aee-4290-402a-9003-7abd8ae85ac1',
            'receiver_id' => '9bff0aee-4f9b-4f55-a41f-7deb48295924',
        ];
        $request = $this->postServerRequest(
            $requestData,
            $this->transactionsEndpoint,
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        assertEquals(201, $response->getStatusCode());
    }

    public function testGetTransaction(): void
    {
        $integration = new CrossBorderIntegration();
        $sep31Service = new Sep31Service($integration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $request = ServerRequestBuilder::getServerRequest($this->transactionsEndpoint . '/9273971203912', []);
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 404, 'transaction not found for id: 9273971203912');

        $txId = '9bff0aff-e8fb-47a7-81bb-0b776501cbb6';

        $request = ServerRequestBuilder::getServerRequest(
            $this->transactionsEndpoint .
            '/' . $txId,
            [],
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        $tx = $this->getTransactionResponse($response);
        assertEquals($txId, $tx->id);
        assertEquals(Sep31TransactionStatus::PENDING_SENDER, $tx->status);
        assertEquals('status message', $tx->statusMessage);
        assertEquals(2500, $tx->statusEta);
        assertEquals('100.0', $tx->amountIn);
        assertNotNull($tx->amountInAsset);
        assertEquals('stellar:USDC:GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN', $tx->amountInAsset);
        assertEquals('110.0', $tx->amountOut);
        assertEquals('iso4217:BRL', $tx->amountOutAsset);
        $feeDetails = $tx->feeDetails;
        assertNotNull($feeDetails);
        assertEquals('10', $feeDetails->total);
        assertEquals('iso4217:BRL', $feeDetails->asset);
        $feeDetailsDetails = $feeDetails->details;
        assertNotNull($feeDetailsDetails);
        assertCount(2, $feeDetailsDetails);
        $detail = $feeDetailsDetails[0];
        assertEquals('Fun fee', $detail->name);
        assertEquals('6.5', $detail->amount);
        assertEquals('just for fun', $detail->description);
        $detail = $feeDetailsDetails[1];
        assertEquals('Service fee', $detail->name);
        assertEquals('3.5', $detail->amount);
        assertEquals('for the service', $detail->description);
        assertEquals('sep6test-a193-4961-861e-57b31fed6eb3', $tx->quoteId);
        assertEquals('GDV2NKAAB5KXKGB7L65HOQ5XEGLXRTUGQET5JH2CNWSUIAHQH3FH7AN3', $tx->stellarAccountId);
        assertEquals('id', $tx->stellarMemoType);
        assertEquals('120190893', $tx->stellarMemo);
        assertNotNull($tx->startedAt);
        assertNotNull($tx->updatedAt);
        assertNotNull($tx->completedAt);
        assertEquals('1234', $tx->stellarTransactionId);
        assertEquals('5678', $tx->externalTransactionId);
        $refunds = $tx->refunds;
        assertNotNull($refunds);
        assertEquals('10', $refunds->amountRefunded);
        assertEquals('2.0', $refunds->amountFee);
        $payments = $refunds->payments;
        assertCount(2, $payments);
        $payment = $payments[0];
        assertEquals('104201', $payment->id);
        assertEquals('5', $payment->amount);
        assertEquals('1.0', $payment->fee);
        $payment = $payments[1];
        assertEquals('104202', $payment->id);
        assertEquals('required info message', $tx->requiredInfoMessage);

        $baseRequiredInfoUpdates = $tx->requiredInfoUpdates;
        assertNotNull($baseRequiredInfoUpdates);
        /**
         * @var array<array-key, mixed> $requiredInfoUpdates
         */
        $requiredInfoUpdates = $baseRequiredInfoUpdates['transaction'];
        /**
         * @var array<array-key, mixed> $cc
         */
        $cc = $requiredInfoUpdates['country_code'];
        assertEquals("The ISO 3166-1 alpha-3 code of the user's current address", $cc['description']);
        assertEquals(['USA', 'BRA'], $cc['choices']);
        assertFalse($cc['optional']);
        /**
         * @var array<array-key, mixed> $dest
         */
        $dest = $requiredInfoUpdates['dest'];
        assertEquals('your bank account number', $dest['description']);
    }

    public function testPutTxCallback(): void
    {
        $integration = new CrossBorderIntegration();
        $sep31Service = new Sep31Service($integration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $txId = '9bff0aff-e8fb-47a7-81bb-0b776501cbb6';

        $request = $this->putServerRequest(
            ['url' => 'https://notsupported.com'],
            $this->transactionsEndpoint . '/' . $txId . '/callback',
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        assertEquals(404, $response->getStatusCode());

        $request = $this->putServerRequest(
            ['url' => 'https://sendinganchor.com/statusCallback'],
            $this->transactionsEndpoint . '/' . $txId . '/callback',
        );
        $response = $sep31Service->handleRequest($request, $sep10Jwt);
        assertEquals(204, $response->getStatusCode());
    }

    private function getTransactionResponse(ResponseInterface $response): SEP31TransactionResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP31TransactionResponse::fromJson($decoded);
    }

    private function getPostTransactionResponse(ResponseInterface $response): SEP31PostTransactionsResponse
    {
        assertEquals(201, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP31PostTransactionsResponse::fromJson($decoded);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function postServerRequest(
        array $parameters,
        string $uri,
    ): ServerRequestInterface {
        return ServerRequestBuilder::serverRequest(
            method: 'POST',
            parameters: $parameters,
            uri: $uri,
            contentType: ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function putServerRequest(
        array $parameters,
        string $uri,
    ): ServerRequestInterface {
        return ServerRequestBuilder::serverRequest(
            method: 'PUT',
            parameters: $parameters,
            uri: $uri,
            contentType: ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
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

    private function getSep31InfoResponse(ResponseInterface $response): SEP31InfoResponse
    {
        self::assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP31InfoResponse::fromJson($decoded);
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
