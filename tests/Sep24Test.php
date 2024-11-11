<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep24\Sep24Service;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\Test\PhpAnchorSdk\callback\InteractiveFlowIntegration;
use ArgoNavis\Test\PhpAnchorSdk\config\AppConfig;
use ArgoNavis\Test\PhpAnchorSdk\config\Sep24Config;
use ArgoNavis\Test\PhpAnchorSdk\util\ServerRequestBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soneso\StellarSDK\SEP\Interactive\SEP24DepositAsset;
use Soneso\StellarSDK\SEP\Interactive\SEP24FeeResponse;
use Soneso\StellarSDK\SEP\Interactive\SEP24InfoResponse;
use Soneso\StellarSDK\SEP\Interactive\SEP24InteractiveResponse;
use Soneso\StellarSDK\SEP\Interactive\SEP24TransactionResponse;
use Soneso\StellarSDK\SEP\Interactive\SEP24TransactionsResponse;
use Soneso\StellarSDK\SEP\Interactive\SEP24WithdrawAsset;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertTrue;
use function assert;
use function error_reporting;
use function intval;
use function is_array;
use function is_string;
use function json_decode;
use function microtime;
use function strval;

use const E_ALL;

class Sep24Test extends TestCase
{
    private string $infoEndpoint = 'https://test.com/sep24/info';
    private string $feeEndpoint = 'https://test.com/sep24/fee';
    private string $depositEndpoint = 'https://test.com/sep24/transactions/deposit/interactive';
    private string $withdrawEndpoint = 'https://test.com/sep24/transactions/withdraw/interactive';
    private string $transactionsEndpoint = 'https://test.com/sep24/transactions';
    private string $transactionEndpoint = 'https://test.com/sep24/transaction';
    private string $accountId = 'GCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6H2M';

    public function setUp(): void
    {
        // Turn on error reporting
        error_reporting(E_ALL);
    }

    public function testGetInfo(): void
    {
        $integration = new InteractiveFlowIntegration();
        $config = new Sep24Config();
        $sep24Service = new Sep24Service(
            sep24Config: $config,
            appConfig: new AppConfig(),
            sep24Integration: $integration,
        );
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $request = ServerRequestBuilder::getServerRequest($this->infoEndpoint, []);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $info = $this->getSep24InfoResponse($response);
        self::assertNotNull($info->depositAssets);
        self::assertNotNull($info->withdrawAssets);
        self::assertNotNull($info->feeEndpointInfo);
        self::assertNotNull($info->featureFlags);

        $depositAssets = $info->depositAssets;
        self::assertArrayHasKey('USD', $depositAssets);
        $usdDepositAsset = $depositAssets['USD'];
        if ($usdDepositAsset instanceof SEP24DepositAsset) {
            assertTrue($usdDepositAsset->enabled);
            assertEquals(5.0, $usdDepositAsset->feeFixed);
            assertEquals(1.0, $usdDepositAsset->feePercent);
            self::assertNull($usdDepositAsset->feeMinimum);
            assertEquals(0.1, $usdDepositAsset->minAmount);
            assertEquals(1000.0, $usdDepositAsset->maxAmount);
        } else {
            self::fail();
        }

        self::assertArrayHasKey('ETH', $depositAssets);
        $ethDepositAsset = $depositAssets['ETH'];
        if ($ethDepositAsset instanceof SEP24DepositAsset) {
            assertTrue($ethDepositAsset->enabled);
            assertEquals(0.002, $ethDepositAsset->feeFixed);
            assertEquals(0.0, $ethDepositAsset->feePercent);
            self::assertNull($ethDepositAsset->feeMinimum);
            self::assertNull($ethDepositAsset->minAmount);
            self::assertNull($ethDepositAsset->maxAmount);
        } else {
            self::fail();
        }

        self::assertArrayHasKey('native', $depositAssets);
        $nativeDepositAsset = $depositAssets['native'];
        if ($nativeDepositAsset instanceof SEP24DepositAsset) {
            assertTrue($nativeDepositAsset->enabled);
            assertEquals(0.00001, $nativeDepositAsset->feeFixed);
            assertEquals(0.0, $nativeDepositAsset->feePercent);
            self::assertNull($nativeDepositAsset->feeMinimum);
            self::assertNull($nativeDepositAsset->minAmount);
            self::assertNull($nativeDepositAsset->maxAmount);
        } else {
            self::fail();
        }

        $withdrawAssets = $info->withdrawAssets;
        self::assertArrayHasKey('USD', $withdrawAssets);
        $usdWithdrawAsset = $withdrawAssets['USD'];
        if ($usdWithdrawAsset instanceof SEP24WithdrawAsset) {
            assertTrue($usdWithdrawAsset->enabled);
            assertEquals(5.0, $usdWithdrawAsset->feeMinimum);
            assertEquals(0.5, $usdWithdrawAsset->feePercent);
            self::assertNull($usdWithdrawAsset->feeFixed);
            assertEquals(0.1, $usdWithdrawAsset->minAmount);
            assertEquals(1000.0, $usdWithdrawAsset->maxAmount);
        } else {
            self::fail();
        }

        self::assertArrayHasKey('ETH', $withdrawAssets);
        $ethWithdrawAsset = $withdrawAssets['ETH'];
        if ($ethWithdrawAsset instanceof SEP24WithdrawAsset) {
            assertFalse($ethWithdrawAsset->enabled);
            self::assertNull($ethWithdrawAsset->feeFixed);
            self::assertNull($ethWithdrawAsset->feePercent);
            self::assertNull($ethWithdrawAsset->feeMinimum);
            self::assertNull($ethWithdrawAsset->minAmount);
            self::assertNull($ethWithdrawAsset->maxAmount);
        } else {
            self::fail();
        }

        self::assertArrayHasKey('native', $withdrawAssets);
        $nativeWithdrawAsset = $withdrawAssets['native'];
        if ($nativeWithdrawAsset instanceof SEP24WithdrawAsset) {
            assertTrue($nativeWithdrawAsset->enabled);
            self::assertNull($nativeWithdrawAsset->feeFixed);
            self::assertNull($nativeWithdrawAsset->feePercent);
            self::assertNull($nativeWithdrawAsset->feeMinimum);
            self::assertNull($nativeWithdrawAsset->minAmount);
            self::assertNull($nativeWithdrawAsset->maxAmount);
        } else {
            self::fail();
        }

        $feeInfo = $info->feeEndpointInfo;
        assertTrue($feeInfo->enabled);

        $featureFlags = $info->featureFlags;
        assertFalse($featureFlags->claimableBalances);
        assertFalse($featureFlags->accountCreation);
    }

    public function testGetFee(): void
    {
        $integration = new InteractiveFlowIntegration();
        $config = new Sep24Config();
        $sep24Service = new Sep24Service(
            sep24Config: $config,
            appConfig: new AppConfig(),
            sep24Integration: $integration,
        );
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $data = ['asset_code' => 'native', 'amount' => '100.0', 'operation' => 'withdraw'];
        $request = ServerRequestBuilder::getServerRequest($this->feeEndpoint, $data);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $feeResponse = $this->getSep24FeeResponse($response);
        self::assertNotNull($feeResponse->fee);
        self::assertEquals(1.00, $feeResponse->fee);

        $data = ['asset_code' => 'native', 'amount' => 100.0, 'operation' => 'deposit'];
        $request = ServerRequestBuilder::getServerRequest($this->feeEndpoint, $data);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $feeResponse = $this->getSep24FeeResponse($response);
        self::assertNotNull($feeResponse->fee);
        self::assertEquals(0.00001, $feeResponse->fee);

        $data = ['asset_code' => 'native', 'amount' => 100.0, 'operation' => 'unknown'];
        $request = ServerRequestBuilder::getServerRequest($this->feeEndpoint, $data);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'unsupported operation type unknown');

        $data = ['asset_code' => 'USD', 'amount' => 10000000.0, 'operation' => 'deposit'];
        $request = ServerRequestBuilder::getServerRequest($this->feeEndpoint, $data);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, "amount exceeds asset's maximum limit of: 1000");

        $data = ['asset_code' => 'USD', 'amount' => 0.01, 'operation' => 'withdraw'];
        $request = ServerRequestBuilder::getServerRequest($this->feeEndpoint, $data);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, "amount is less than asset's minimum limit of: 0.1");

        $config->feeEndpointEnabled = false;
        $sep24Service = new Sep24Service(
            sep24Config: $config,
            appConfig: new AppConfig(),
            sep24Integration: $integration,
        );
        $data = ['asset_code' => 'native', 'amount' => 100.0, 'operation' => 'deposit'];
        $request = ServerRequestBuilder::getServerRequest($this->feeEndpoint, $data);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 404, 'Fee endpoint is not supported.');
    }

    public function testInteractive(): void
    {
        $integration = new InteractiveFlowIntegration();
        $config = new Sep24Config();
        $sep24Service = new Sep24Service(
            sep24Config: $config,
            appConfig: new AppConfig(),
            sep24Integration: $integration,
        );
        $sep10Jwt = $this->createSep10Jwt($this->accountId);

        // deposit
        $usd = new IdentificationFormatAsset(IdentificationFormatAsset::ASSET_SCHEMA_ISO4217, 'USD');
        $externalTxId = '17177263';
        $depositData = ['asset_code' => 'ETH',
            'source_asset' => $usd->getStringRepresentation(),
            'wallet_url' => $externalTxId, // hack for testing
        ];
        $request = $this->postServerRequest(
            $depositData,
            $this->depositEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_MULTIPART_FORM_DATA,
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $interactiveResponse = $this->getSep24InteractiveResponse($response);
        self::assertEquals('interactive_customer_info_needed', $interactiveResponse->type);
        assertStringContainsString($interactiveResponse->id, $interactiveResponse->url);
        $txId = $interactiveResponse->id;

        // get transaction by id
        $request = ServerRequestBuilder::getServerRequest($this->transactionEndpoint, ['id' => $txId]);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $txResponse = $this->getSep24TransactionResponse($response);
        assertEquals($txId, $txResponse->transaction->id);
        // do some more checks

        // get transaction by external id
        $request = ServerRequestBuilder::getServerRequest(
            $this->transactionEndpoint,
            ['external_transaction_id' => $externalTxId],
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $txResponse = $this->getSep24TransactionResponse($response);
        assertEquals($txId, $txResponse->transaction->id);

        // add quote id
        $depositData = [
            'asset_code' => 'ETH',
            'asset_issuer' => 'GDLW7I64UY2HG4PWVJB2KYLG5HWPRCIDD3WUVRFJMJASX4CB7HJVDOYP',
            'source_asset' => $usd->getStringRepresentation(),
            'amount' => '542',
            'quote_id' => 'de762cda-a193-4961-861e-57b31fed6eb3',
        ];
        $request = $this->postServerRequest(
            $depositData,
            $this->depositEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_MULTIPART_FORM_DATA,
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $interactiveResponse = $this->getSep24InteractiveResponse($response);
        self::assertEquals('interactive_customer_info_needed', $interactiveResponse->type);

        // check quote errors
        $depositData = [
            'asset_code' => 'ETHA',
            'source_asset' => $usd->getStringRepresentation(),
            'amount' => '542',
            'quote_id' => 'de762cda-a193-4961-861e-57b31fed6eb3',
        ];
        $request = $this->postServerRequest(
            $depositData,
            $this->depositEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_MULTIPART_FORM_DATA,
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        assert($response->getStatusCode() === 400);

        $depositData = [
            'asset_code' => 'ETH',
            'asset_issuer' => 'GDLW7I64UY2HG4PWVJB2KYLG5HWPRCIDD3WUVRFJMJASX4CB7HJVDOYP',
            'source_asset' => $usd->getStringRepresentation(),
            'amount' => '999',
            'quote_id' => 'de762cda-a193-4961-861e-57b31fed6eb3',
        ];
        $request = $this->postServerRequest(
            $depositData,
            $this->depositEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_MULTIPART_FORM_DATA,
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        assert($response->getStatusCode() === 400);

        $depositData = [
            'asset_code' => 'ETH',
            'asset_issuer' => 'GDLW7I64UY2HG4PWVJB2KYLG5HWPRCIDD3WUVRFJMJASX4CB7HJVDOYP',
            'source_asset' => 'stellar:native',
            'amount' => '542',
            'quote_id' => 'de762cda-a193-4961-861e-57b31fed6eb3',
        ];
        $request = $this->postServerRequest(
            $depositData,
            $this->depositEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_MULTIPART_FORM_DATA,
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        assert($response->getStatusCode() === 400);

        // withdraw
        $usd = new IdentificationFormatAsset(IdentificationFormatAsset::ASSET_SCHEMA_ISO4217, 'USD');
        $withdrawData = ['asset_code' => 'native', 'destination_asset' => $usd->getStringRepresentation()];
        $request = $this->postServerRequest(
            $withdrawData,
            $this->withdrawEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        // repeat for history test
        $sep24Service->handleRequest($request, $sep10Jwt);
        $sep24Service->handleRequest($request, $sep10Jwt);

        // set stellar tx id for test and repeat
        $stellarTxId = '3945b1f86bf40b235f955919fb9cadf236950a83091835a064d8cc3926bf64c0';
        $withdrawData['wallet_name'] = $stellarTxId; // hack for test
        $request = $this->postServerRequest(
            $withdrawData,
            $this->withdrawEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);

        $interactiveResponse = $this->getSep24InteractiveResponse($response);
        self::assertEquals('interactive_customer_info_needed', $interactiveResponse->type);
        assertStringContainsString($interactiveResponse->id, $interactiveResponse->url);
        $txId = $interactiveResponse->id;

        // get transaction by id
        $request = ServerRequestBuilder::getServerRequest($this->transactionEndpoint, ['id' => $txId]);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $txResponse = $this->getSep24TransactionResponse($response);
        assertEquals($txId, $txResponse->transaction->id);

        // get transaction by stellar tx id
        $request = ServerRequestBuilder::getServerRequest(
            $this->transactionEndpoint,
            ['stellar_transaction_id' => $stellarTxId],
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $txResponse = $this->getSep24TransactionResponse($response);
        assertEquals($txId, $txResponse->transaction->id);

        // get transaction history
        $request = ServerRequestBuilder::getServerRequest($this->transactionsEndpoint, ['asset_code' => 'native']);
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $txsResponse = $this->getSep24TransactionsResponse($response);
        assertCount(3, $txsResponse->transactions);

        // add quote id
        $withdrawData = [
            'asset_code' => 'native',
            'destination_asset' => $usd->getStringRepresentation(),
            'amount' => '100',
            'quote_id' => 'aa332xdr-a123-9999-543t-57b31fed6eb3',
        ];
        $request = $this->postServerRequest(
            $withdrawData,
            $this->withdrawEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        $interactiveResponse = $this->getSep24InteractiveResponse($response);
        self::assertEquals('interactive_customer_info_needed', $interactiveResponse->type);

        // check quote errors.
        $withdrawData = [
            'asset_code' => 'native',
            'destination_asset' => $usd->getStringRepresentation(),
            'amount' => '101',
            'quote_id' => 'aa332xdr-a123-9999-543t-57b31fed6eb3',
        ];
        $request = $this->postServerRequest(
            $withdrawData,
            $this->withdrawEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        assert($response->getStatusCode() === 400);

        $withdrawData = [
            'asset_code' => $usd->getStringRepresentation(),
            'destination_asset' => 'native',
            'amount' => '100',
            'quote_id' => 'aa332xdr-a123-9999-543t-57b31fed6eb3',
        ];
        $request = $this->postServerRequest(
            $withdrawData,
            $this->withdrawEndpoint,
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep24Service->handleRequest($request, $sep10Jwt);
        assert($response->getStatusCode() === 400);
    }

    private function getSep24InfoResponse(ResponseInterface $response): SEP24InfoResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP24InfoResponse::fromJson($decoded);
    }

    private function getSep24FeeResponse(ResponseInterface $response): SEP24FeeResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP24FeeResponse::fromJson($decoded);
    }

    private function getSep24InteractiveResponse(ResponseInterface $response): SEP24InteractiveResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP24InteractiveResponse::fromJson($decoded);
    }

    private function getSep24TransactionResponse(ResponseInterface $response): SEP24TransactionResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP24TransactionResponse::fromJson($decoded);
    }

    private function getSep24TransactionsResponse(ResponseInterface $response): SEP24TransactionsResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP24TransactionsResponse::fromJson($decoded);
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
    private function postServerRequest(
        array $parameters,
        string $uri,
        string $contentType,
        ?array $files = null,
    ): ServerRequestInterface {
        return ServerRequestBuilder::serverRequest('POST', $parameters, $uri, $contentType, $files);
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
