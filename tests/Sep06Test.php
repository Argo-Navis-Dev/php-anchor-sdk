<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep06\Sep06Service;
use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\shared\Sep06TransactionStatus;
use ArgoNavis\Test\PhpAnchorSdk\callback\QuotesIntegration;
use ArgoNavis\Test\PhpAnchorSdk\callback\TransferIntegration;
use ArgoNavis\Test\PhpAnchorSdk\config\AppConfig;
use ArgoNavis\Test\PhpAnchorSdk\config\Sep06Config;
use ArgoNavis\Test\PhpAnchorSdk\util\ServerRequestBuilder;
use Psr\Http\Message\ResponseInterface;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\SEP\TransferServerService\AnchorTransactionResponse;
use Soneso\StellarSDK\SEP\TransferServerService\AnchorTransactionsResponse;
use Soneso\StellarSDK\SEP\TransferServerService\DepositResponse;
use Soneso\StellarSDK\SEP\TransferServerService\InfoResponse;
use Soneso\StellarSDK\SEP\TransferServerService\WithdrawResponse;
use Soneso\StellarSDK\Util\FriendBot;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertTrue;
use function assert;
use function error_reporting;
use function intval;
use function is_array;
use function is_string;
use function json_decode;
use function microtime;
use function sleep;
use function strval;
use function uniqid;

use const E_ALL;

class Sep06Test extends TestCase
{
    private string $infoEndpoint = 'https://test.com/sep06/info';
    private string $depositEndpoint = 'https://test.com/sep06/deposit';
    private string $depositExchangeEndpoint = 'https://test.com/sep06/deposit-exchange';
    private string $withdrawEndpoint = 'https://test.com/sep06/withdraw';
    private string $withdrawExchangeEndpoint = 'https://test.com/sep06/withdraw-exchange';
    private string $transactionsEndpoint = 'https://test.com/sep06/transactions';
    private string $transactionEndpoint = 'https://test.com/sep06/transaction';

    private string $accountId;

    public function setUp(): void
    {
        // Turn on error reporting
        error_reporting(E_ALL);
        $this->accountId = KeyPair::random()->getAccountId();
    }

    public function testGetInfo(): void
    {
        $integration = new TransferIntegration();
        $config = new Sep06Config();
        $appConfig = new AppConfig();
        $sep06Service = new Sep06Service(appConfig: $appConfig, sep06Config: $config, sep06Integration: $integration);

        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $request = ServerRequestBuilder::getServerRequest($this->infoEndpoint, []);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $info = $this->getInfoResponse($response);
        self::assertNotNull($info->depositAssets);
        self::assertNotNull($info->withdrawAssets);
        self::assertNotNull($info->depositExchangeAssets);
        self::assertNotNull($info->withdrawExchangeAssets);
        self::assertNotNull($info->feeInfo);
        self::assertNotNull($info->featureFlags);
        self::assertNotNull($info->transactionInfo);
        self::assertNotNull($info->transactionsInfo);

        $depositAssets = $info->depositAssets;
        self::assertArrayHasKey('USDC', $depositAssets);
        $usdcDepositAsset = $depositAssets['USDC'];
        assertTrue($usdcDepositAsset->enabled);
        assertTrue($usdcDepositAsset->authenticationRequired);
        assertEquals(0.1, $usdcDepositAsset->minAmount);
        assertEquals(1000.0, $usdcDepositAsset->maxAmount);
        $usdcFields = $usdcDepositAsset->fields;
        self::assertNotNull($usdcFields);
        $usdcFieldType = $usdcFields['type'] ?? null;
        self::assertNotNull($usdcFieldType);
        assertEquals('type of deposit to make', $usdcFieldType->description);
        assertEquals(['SEPA', 'SWIFT', 'cash'], $usdcFieldType->choices);

        self::assertArrayHasKey('ETH', $depositAssets);
        $ethDepositAsset = $depositAssets['ETH'];
        assertTrue($ethDepositAsset->enabled);
        assertTrue($ethDepositAsset->authenticationRequired);

        $depositExchangeAssets = $info->depositExchangeAssets;
        self::assertArrayHasKey('USDC', $depositExchangeAssets);
        $usdcDepositExchangeAsset = $depositExchangeAssets['USDC'];
        assertTrue($usdcDepositExchangeAsset->enabled);
        assertTrue($usdcDepositExchangeAsset->authenticationRequired);
        $usdcFields = $usdcDepositExchangeAsset->fields;
        self::assertNotNull($usdcFields);
        $usdcFieldType = $usdcFields['type'] ?? null;
        self::assertNotNull($usdcFieldType);
        assertEquals('type of deposit to make', $usdcFieldType->description);
        assertEquals(['SEPA', 'SWIFT', 'cash'], $usdcFieldType->choices);

        $ethDepositExchangeAsset = $depositExchangeAssets['ETH'] ?? null;
        assertNull($ethDepositExchangeAsset);

        $withdrawAssets = $info->withdrawAssets;
        self::assertArrayHasKey('USDC', $withdrawAssets);
        $usdcWithdrawAsset = $withdrawAssets['USDC'];
        assertTrue($usdcWithdrawAsset->enabled);
        assertTrue($usdcWithdrawAsset->authenticationRequired);
        assertEquals(0.1, $usdcWithdrawAsset->minAmount);
        assertEquals(1000.0, $usdcWithdrawAsset->maxAmount);
        $types = $usdcWithdrawAsset->types;
        assertNotNull($types);
        self::assertArrayHasKey('bank_account', $types);
        self::assertArrayHasKey('cash', $types);

        self::assertArrayHasKey('ETH', $withdrawAssets);
        $ethWithdrawAsset = $withdrawAssets['ETH'];
        assertFalse($ethWithdrawAsset->enabled);

        $withdrawExchangeAssets = $info->withdrawExchangeAssets;
        self::assertArrayHasKey('USDC', $withdrawExchangeAssets);
        $usdcWithdrawExchangeAsset = $withdrawExchangeAssets['USDC'];
        assertTrue($usdcWithdrawExchangeAsset->enabled);
        assertTrue($usdcWithdrawExchangeAsset->authenticationRequired);
        $types = $usdcWithdrawExchangeAsset->types;
        assertNotNull($types);
        self::assertArrayHasKey('bank_account', $types);
        self::assertArrayHasKey('cash', $types);

        $feeInfo = $info->feeInfo;
        assertFalse($feeInfo->enabled);
        assertEquals('Fee endpoint is not supported.', $feeInfo->description);

        $txInfo = $info->transactionInfo;
        assertTrue($txInfo->enabled);
        assertTrue($txInfo->authenticationRequired);

        $txsInfo = $info->transactionsInfo;
        assertTrue($txsInfo->enabled);
        assertTrue($txsInfo->authenticationRequired);

        $featureFlags = $info->featureFlags;
        assertFalse($featureFlags->claimableBalances);
        //assertFalse($featureFlags->accountCreation);
    }

    public function testDeposit(): void
    {
        $integration = new TransferIntegration();
        $config = new Sep06Config();
        $appConfig = new AppConfig();
        $sep06Service = new Sep06Service(appConfig: $appConfig, sep06Config: $config, sep06Integration: $integration);

        $request = ServerRequestBuilder::getServerRequest($this->depositEndpoint, []);
        $response = $sep06Service->handleRequest($request);
        assertEquals(403, $response->getStatusCode());
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing asset_code');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            ['asset_code' => 'USDC'],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing account');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => '93939933',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid account, must be a valid account id');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'memo' => '122',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'memo type must be provided if memo is provided');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'memo' => '122',
                'memo_type' => 'donuts',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'memo type donuts not supported.');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => '8999',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'Invalid type 8999 for asset USDC. Supported types are SEPA, SWIFT, cash.',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => 'cash',
                'amount' => '-10.0',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'invalid amount -10 for asset USDC',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => 'cash',
                'amount' => '0.001',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'invalid amount 0.001 for asset USDC',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => 'cash',
                'amount' => '1000000.001',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'invalid amount 1000000.001 for asset USDC',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => 'cash',
                'amount' => '1000000.001',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'invalid amount 1000000.001 for asset USDC',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => 'cash',
                'amount' => '100',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'Account creation not supported. Account ' . $this->accountId . ' not found.',
        );
        FriendBot::fundTestAccount($this->accountId);
        sleep(5);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        assertEquals(200, $response->getStatusCode());
        $depositResponse = $this->getDepositResponse($response);
        assertEquals(45, $depositResponse->eta);
        assertEquals(0.1, $depositResponse->minAmount);
        assertEquals(0.5, $depositResponse->feeFixed);
        assertEquals(2.0, $depositResponse->feePercent);
        assertEquals('extra info test', $depositResponse->extraInfo?->message);
        assertEquals('Make a payment to Bank: 121122676 Account: 13719713158835300', $depositResponse->how);
        $instructions = $depositResponse->instructions;
        assertNotNull($instructions);
        assertCount(2, $instructions);

        assertArrayHasKey('bank_number', $instructions);
        $bankNr = $instructions['bank_number'];
        assertEquals('121122676', $bankNr->value);
        assertEquals('US bank routing number', $bankNr->description);

        assertArrayHasKey('bank_account_number', $instructions);
        $bankAccNr = $instructions['bank_account_number'];
        assertEquals('13719713158835300', $bankAccNr->value);
        assertEquals('US bank account number', $bankAccNr->description);
    }

    public function testDepositExchange(): void
    {
        $integration = new TransferIntegration();
        $config = new Sep06Config();
        $appConfig = new AppConfig();
        $quotesIntegration = new QuotesIntegration();
        $sep06Service = new Sep06Service(
            appConfig: $appConfig,
            sep06Config: $config,
            sep06Integration: $integration,
            quotesIntegration: $quotesIntegration,
        );

        $request = ServerRequestBuilder::getServerRequest($this->depositExchangeEndpoint, []);
        $response = $sep06Service->handleRequest($request);
        assertEquals(403, $response->getStatusCode());
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing destination_asset');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            ['destination_asset' => 'USDC'],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing source_asset');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing amount');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '20.0',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing account');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '20.0',
                'account' => '192938833',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid account, must be a valid account id');

        FriendBot::fundTestAccount($this->accountId);
        sleep(5);
        // print($this->accountId .PHP_EOL);
        // $this->accountId = 'GB22O5I5IHKKE56K6TACGEAN7FBD5B7UEHL7VL2B6UVTHC5XKHXWM2CM';

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '-10.0',
                'account' => $this->accountId,
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid amount -10 for asset USDC');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '0.0001',
                'account' => $this->accountId,
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid amount 0.0001 for asset USDC');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '10000000000.01',
                'account' => $this->accountId,
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid amount 10000000000.01 for asset USDC');

        $notFundedAcc = KeyPair::random()->getAccountId();
        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '100.0',
                'account' => $notFundedAcc,
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Account creation not supported. Account ' . $notFundedAcc . ' not found.');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:XCY',
                'amount' => '100.00',
                'account' => $this->accountId,
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid operation for asset iso4217:XCY');

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '100.00',
                'account' => $this->accountId,
                'type' => 'bash',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            response: $response,
            statusCode: 400,
            message: 'Invalid type bash for asset USDC. Supported types are SEPA, SWIFT, cash.',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '100.00',
                'account' => $this->accountId,
                'type' => 'cash',
                'quote_id' => '989829839283',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            response: $response,
            statusCode: 400,
            message: 'quote not found for id: 989829839283',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '100.00',
                'account' => $this->accountId,
                'type' => 'cash',
                'quote_id' => 'de762cda-a193-4961-861e-57b31fed6eb3',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            response: $response,
            statusCode: 400,
            message: 'quote amount does not match request amount',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->depositExchangeEndpoint,
            [
                'destination_asset' => 'USDC',
                'source_asset' => 'iso4217:BRL',
                'amount' => '542.00',
                'account' => $this->accountId,
                'type' => 'cash',
                'quote_id' => 'de762cda-a193-4961-861e-57b31fed6eb3',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        assertEquals(200, $response->getStatusCode());
        $depositResponse = $this->getDepositResponse($response);
        assertEquals(45, $depositResponse->eta);
    }

    public function testWithdraw(): void
    {
        $integration = new TransferIntegration();
        $config = new Sep06Config();
        $appConfig = new AppConfig();
        $sep06Service = new Sep06Service(appConfig: $appConfig, sep06Config: $config, sep06Integration: $integration);

        $request = ServerRequestBuilder::getServerRequest($this->withdrawEndpoint, []);
        $response = $sep06Service->handleRequest($request);
        assertEquals(403, $response->getStatusCode());
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing asset_code');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawEndpoint,
            ['asset_code' => 'USDC'],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing type');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawEndpoint,
            [
                'asset_code' => 'USDC',
                'type' => 'cash',
                'account' => '982394823948',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid account, must be a valid account id');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawEndpoint,
            [
                'asset_code' => 'USDC',
                'type' => '8999',
                'account' => $this->accountId,
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'Invalid type 8999 for asset USDC. Supported types are bank_account, cash.',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => 'cash',
                'amount' => '-10.0',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'invalid amount -10 for asset USDC',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => 'cash',
                'amount' => '0.001',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'invalid amount 0.001 for asset USDC',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => 'cash',
                'amount' => '1000000.001',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError(
            $response,
            400,
            'invalid amount 1000000.001 for asset USDC',
        );

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawEndpoint,
            [
                'asset_code' => 'USDC',
                'account' => $this->accountId,
                'type' => 'cash',
                'amount' => '100',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        assertEquals(200, $response->getStatusCode());
        $withdrawResponse = $this->getWithdrawResponse($response);
        assertEquals(45, $withdrawResponse->eta);
        assertEquals(0.1, $withdrawResponse->minAmount);
        assertEquals(1000.00, $withdrawResponse->maxAmount);
        assertEquals(0.5, $withdrawResponse->feeFixed);
        assertEquals(0.2, $withdrawResponse->feePercent);
        assertEquals('extra test', $withdrawResponse->extraInfo?->message);
        assertEquals('GCKKKJW2RY2YWZGEW7SSY3H7J2MMHYSEQQ7JOHBWORYBGCB2ZDKJ6VFA', $withdrawResponse->accountId);
        assertEquals('id', $withdrawResponse->memoType);
        assertEquals('19233', $withdrawResponse->memo);
    }

    public function testWithdrawExchange(): void
    {
        $integration = new TransferIntegration();
        $config = new Sep06Config();
        $appConfig = new AppConfig();
        $quotesIntegration = new QuotesIntegration();
        $sep06Service = new Sep06Service(
            appConfig: $appConfig,
            sep06Config: $config,
            sep06Integration: $integration,
            quotesIntegration: $quotesIntegration,
        );

        $request = ServerRequestBuilder::getServerRequest($this->withdrawExchangeEndpoint, []);
        $response = $sep06Service->handleRequest($request);
        assertEquals(403, $response->getStatusCode());
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing source_asset');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            ['source_asset' => 'USDC'],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing destination_asset');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:BRL',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing amount');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:BRL',
                'amount' => '500',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'missing type');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:BRL',
                'amount' => '500',
                'type' => 'donut',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Invalid type donut for asset USDC. Supported types are bank_account, cash.');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:BRL',
                'amount' => '-10',
                'type' => 'cash',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid amount -10 for asset USDC');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:BRL',
                'amount' => '0.0001',
                'type' => 'cash',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid amount 0.0001 for asset USDC');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:BRL',
                'amount' => '100000000',
                'type' => 'cash',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid amount 100000000 for asset USDC');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:BRL',
                'amount' => '500',
                'type' => 'cash',
                'quote_id' => '19092091',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'quote not found for id: 19092091');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:BRL',
                'amount' => '500',
                'type' => 'cash',
                'quote_id' => 'sep6test-a193-4961-861e-57b31fed6eb3',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'quote amount does not match request amount');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:RON',
                'amount' => '542',
                'type' => 'cash',
                'quote_id' => 'sep6test-a193-4961-861e-57b31fed6eb3',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'invalid operation for asset iso4217:RON');

        $request = ServerRequestBuilder::getServerRequest(
            $this->withdrawExchangeEndpoint,
            [
                'source_asset' => 'USDC',
                'destination_asset' => 'iso4217:BRL',
                'amount' => '542',
                'type' => 'cash',
                'quote_id' => 'sep6test-a193-4961-861e-57b31fed6eb3',
            ],
        );
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        assertEquals(200, $response->getStatusCode());
        $withdrawResponse = $this->getWithdrawResponse($response);
        assertEquals(45, $withdrawResponse->eta);
        assertEquals(0.1, $withdrawResponse->minAmount);
        assertEquals(1000.00, $withdrawResponse->maxAmount);
        assertEquals(0.5, $withdrawResponse->feeFixed);
        assertEquals(0.2, $withdrawResponse->feePercent);
        assertEquals('extra test', $withdrawResponse->extraInfo?->message);
        assertEquals('GCKKKJW2RY2YWZGEW7SSY3H7J2MMHYSEQQ7JOHBWORYBGCB2ZDKJ6VFA', $withdrawResponse->accountId);
        assertEquals('id', $withdrawResponse->memoType);
        assertEquals('19233', $withdrawResponse->memo);
    }

    public function testTransactions(): void
    {
        $integration = new TransferIntegration();
        $config = new Sep06Config();
        $appConfig = new AppConfig();
        $quotesIntegration = new QuotesIntegration();
        $sep06Service = new Sep06Service(
            appConfig: $appConfig,
            sep06Config: $config,
            sep06Integration: $integration,
            quotesIntegration: $quotesIntegration,
        );

        $txId = uniqid();
        $request = ServerRequestBuilder::getServerRequest($this->transactionEndpoint, ['id' => $txId]);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        assertEquals(200, $response->getStatusCode());
        $txResponse = $this->getAnchorTransactionResponse($response);
        $tx = $txResponse->transaction;
        assertEquals($txId, $tx->id);
        assertEquals('withdraw', $tx->kind);
        assertEquals(Sep06TransactionStatus::COMPLETED, $tx->status);
        assertEquals(2500, $tx->statusEta);
        assertEquals('https://test.com/more/' . $txId, $tx->moreInfoUrl);
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
        assertEquals($this->accountId, $tx->from);
        assertEquals('GB29 NWBK 6016 1331 9268 19', $tx->to);
        assertEquals('external extra test', $tx->externalExtra);
        assertEquals('external extra text test', $tx->externalExtraText);
        assertEquals('deposit-123', $tx->depositMemo);
        assertEquals('text', $tx->depositMemoType);
        assertEquals('GDV2NKAAB5KXKGB7L65HOQ5XEGLXRTUGQET5JH2CNWSUIAHQH3FH7AN3', $tx->withdrawAnchorAccount);
        assertEquals('771626434', $tx->withdrawMemo);
        assertEquals('id', $tx->withdrawMemoType);
        assertNotNull($tx->startedAt);
        assertNotNull($tx->updatedAt);
        assertNotNull($tx->completedAt);
        assertNull($tx->stellarTransactionId);
        assertNull($tx->externalTransactionId);
        assertEquals('hi', $tx->message);
        $refunds = $tx->refunds;
        assertNotNull($refunds);
        assertEquals('10', $refunds->amountRefunded);
        assertEquals('2.0', $refunds->amountFee);
        $payments = $refunds->payments;
        assertCount(2, $payments);
        $payment = $payments[0];
        assertEquals('104201', $payment->id);
        assertEquals('external', $payment->idType);
        assertEquals('5', $payment->amount);
        assertEquals('1.0', $payment->fee);
        $payment = $payments[1];
        assertEquals('104202', $payment->id);
        assertEquals('test required info message', $tx->requiredInfoMessage);
        $requiredInfoUpdates = $tx->requiredInfoUpdates;
        assertNotNull($requiredInfoUpdates);
        $cc = $requiredInfoUpdates['country_code'];
        assertNotNull($cc);
        assertEquals("The ISO 3166-1 alpha-3 code of the user's current address", $cc->description);
        assertEquals(['USA', 'BRA'], $cc->choices);
        assertFalse($cc->optional);
        $dest = $requiredInfoUpdates['dest'];
        assertNotNull($dest);
        assertEquals('your bank account number', $dest->description);
        $instructions = $tx->instructions;
        assertNotNull($instructions);
        assertCount(2, $instructions);

        assertArrayHasKey('bank_number', $instructions);
        $bankNr = $instructions['bank_number'];
        assertEquals('121122676', $bankNr->value);
        assertEquals('US bank routing number', $bankNr->description);

        assertArrayHasKey('bank_account_number', $instructions);
        $bankAccNr = $instructions['bank_account_number'];
        assertEquals('13719713158835300', $bankAccNr->value);
        assertEquals('US bank account number', $bankAccNr->description);
        self::assertEquals(
            '000000000a12cd57c169a34e7794bdcdf2d093fab135c59ea599e2d1233d7a53f26c1464',
            $tx->claimableBalanceId,
        );

        $stellarTxId = 'b9d0b2292c4e09e8eb22d036171491e87b8d2086bf8b265874c8d182cb9c9020';
        $request = ServerRequestBuilder::getServerRequest(
            $this->transactionEndpoint,
            ['stellar_transaction_id' => $stellarTxId],
        );
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        assertEquals(200, $response->getStatusCode());
        $txResponse = $this->getAnchorTransactionResponse($response);
        $tx = $txResponse->transaction;
        assertEquals($stellarTxId, $tx->stellarTransactionId);

        $externalTxId = '8928398249389489234';
        $request = ServerRequestBuilder::getServerRequest(
            $this->transactionEndpoint,
            ['external_transaction_id' => $externalTxId],
        );
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        assertEquals(200, $response->getStatusCode());
        $txResponse = $this->getAnchorTransactionResponse($response);
        $tx = $txResponse->transaction;
        assertEquals($externalTxId, $tx->externalTransactionId);

        $request = ServerRequestBuilder::getServerRequest(
            $this->transactionsEndpoint,
            ['asset_code' => 'USDC', 'account' => $this->accountId],
        );
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $response = $sep06Service->handleRequest($request, $sep10Jwt);
        assertEquals(200, $response->getStatusCode());
        $transactions = $this->getAnchorTransactionsResponse($response);
        assertCount(3, $transactions->transactions);
    }

    private function getDepositResponse(ResponseInterface $response): DepositResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return DepositResponse::fromJson($decoded);
    }

    private function getWithdrawResponse(ResponseInterface $response): WithdrawResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return WithdrawResponse::fromJson($decoded);
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

    private function getInfoResponse(ResponseInterface $response): InfoResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return InfoResponse::fromJson($decoded);
    }

    private function getAnchorTransactionResponse(ResponseInterface $response): AnchorTransactionResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return AnchorTransactionResponse::fromJson($decoded);
    }

    private function getAnchorTransactionsResponse(ResponseInterface $response): AnchorTransactionsResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return AnchorTransactionsResponse::fromJson($decoded);
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
