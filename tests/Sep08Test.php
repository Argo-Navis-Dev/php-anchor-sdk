<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep08\Sep08Service;
use ArgoNavis\Test\PhpAnchorSdk\callback\RegulatedAssetsIntegration;
use ArgoNavis\Test\PhpAnchorSdk\util\ServerRequestBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\AllowTrustOperationBuilder;
use Soneso\StellarSDK\AssetTypeCreditAlphanum4;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\FeeBumpTransactionBuilder;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\PaymentOperation;
use Soneso\StellarSDK\PaymentOperationBuilder;
use Soneso\StellarSDK\SEP\RegulatedAssets\SEP08PostTransactionActionRequired;
use Soneso\StellarSDK\SEP\RegulatedAssets\SEP08PostTransactionPending;
use Soneso\StellarSDK\SEP\RegulatedAssets\SEP08PostTransactionRejected;
use Soneso\StellarSDK\SEP\RegulatedAssets\SEP08PostTransactionResponse;
use Soneso\StellarSDK\SEP\RegulatedAssets\SEP08PostTransactionRevised;
use Soneso\StellarSDK\SEP\RegulatedAssets\SEP08PostTransactionSuccess;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\TransactionBuilder;
use phpseclib3\Math\BigInteger;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertTrue;
use function assert;
use function count;
use function error_reporting;
use function is_array;
use function is_string;
use function json_decode;

use const E_ALL;

class Sep08Test extends TestCase
{
    private string $approveEndpoint = 'https://test.com/sep08/tx_approve';

    private KeyPair $senderKeyPair;
    private string $destinationAccountId = 'GC63ATVAHXHE3EUB4IW5DWOJWRCHKBSWU4W5L53LW5FQ2GKNAI33RYQP';
    private string $regulatedAssetCode = 'REG';
    private string $regulatedAssetIssuer = 'GDFVH6M4CBNOSCI3QMUPGUEIW5YA6OYLCSJGSNEYLFL4IB62KZMOMXSF';
    private PaymentOperation $paymentOperation;
    private Account $sourceAccount;

    public function setUp(): void
    {
        // Turn on error reporting
        error_reporting(E_ALL);
        $this->senderKeyPair = KeyPair::random();
        $regulatedAsset = new AssetTypeCreditAlphanum4($this->regulatedAssetCode, $this->regulatedAssetIssuer);
        $this->paymentOperation = (new PaymentOperationBuilder(
            $this->destinationAccountId,
            $regulatedAsset,
            '100',
        ))->build();
        $this->sourceAccount = new Account($this->senderKeyPair->getAccountId(), new BigInteger(1));
    }

    public function testValidation(): void
    {
        $integration = new RegulatedAssetsIntegration();
        $sep08Service = new Sep08Service($integration);
        $data = ['tx' => 'blubber'];
        $request = ServerRequestBuilder::getServerRequest($this->approveEndpoint, $data);
        $response = $sep08Service->handleRequest($request);
        $this->checkError($response, 400, 'Invalid request. Method not supported.');
        $request = $this->postServerRequest(
            $data,
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep08Service->handleRequest($request);
        assertEquals(400, $response->getStatusCode());
        $request = $this->postServerRequest(
            $data,
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_URLENCODED,
        );
        $response = $sep08Service->handleRequest($request);
        assertEquals(400, $response->getStatusCode());

        $txBuilder = new TransactionBuilder($this->sourceAccount);
        $txBuilder->addOperation($this->paymentOperation);
        $tx = $txBuilder->build();

        $txXdr = $tx->toEnvelopeXdrBase64();
        $request = $this->postServerRequest(
            ['tx' => $txXdr],
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep08Service->handleRequest($request);
        $this->checkError(
            $response,
            400,
            'Invalid request. Transaction has no signatures.',
        );
        $tx->sign($this->senderKeyPair, Network::testnet());
        $txXdr = $tx->toEnvelopeXdrBase64();
        $request = $this->postServerRequest(
            ['tx' => $txXdr],
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep08Service->handleRequest($request);
        assertEquals(200, $response->getStatusCode());
    }

    public function testSuccess(): void
    {
        $integration = new RegulatedAssetsIntegration();
        $sep08Service = new Sep08Service($integration);
        $txBuilder = new TransactionBuilder($this->sourceAccount);
        /*
         * Operation 1: AllowTrust op where issuer fully authorizes account A, asset X
         * Operation 2: AllowTrust op where issuer fully authorizes account B, asset X
         * Operation 3: Payment from A to B
         * Operation 4: AllowTrust op where issuer fully deauthorizes account B, asset X
         * Operation 5: AllowTrust op where issuer fully deauthorizes account A, asset X
         *
         */
        $allowTrustAOp = (
            new AllowTrustOperationBuilder(
                trustor: $this->sourceAccount->getAccountId(),
                assetCode: $this->regulatedAssetCode,
                authorized: true,
                authorizedToMaintainLiabilities: false,
            )
        )->setSourceAccount($this->regulatedAssetIssuer)->build();

        $allowTrustBOp = (
            new AllowTrustOperationBuilder(
                trustor: $this->destinationAccountId,
                assetCode: $this->regulatedAssetCode,
                authorized:true,
                authorizedToMaintainLiabilities: false,
            )
        )->setSourceAccount($this->regulatedAssetIssuer)->build();

        $disAllowTrustBOp = (
            new AllowTrustOperationBuilder(
                trustor: $this->destinationAccountId,
                assetCode: $this->regulatedAssetCode,
                authorized:false,
                authorizedToMaintainLiabilities: false,
            )
        )->setSourceAccount($this->regulatedAssetIssuer)->build();

        $disAllowTrustAOp = (
            new AllowTrustOperationBuilder(
                trustor: $this->sourceAccount->getAccountId(),
                assetCode: $this->regulatedAssetCode,
                authorized: false,
                authorizedToMaintainLiabilities: false,
            )
        )->setSourceAccount($this->regulatedAssetIssuer)->build();

        $txBuilder->addOperation($allowTrustAOp);
        $txBuilder->addOperation($allowTrustBOp);
        $txBuilder->addOperation($this->paymentOperation);
        $txBuilder->addOperation($disAllowTrustBOp);
        $txBuilder->addOperation($disAllowTrustAOp);

        $tx = $txBuilder->build();
        $tx->sign($this->senderKeyPair, Network::testnet());
        $txXdr = $tx->toEnvelopeXdrBase64();
        $request = $this->postServerRequest(
            ['tx' => $txXdr],
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep08Service->handleRequest($request);
        assertEquals(200, $response->getStatusCode());
        $sep08Response = $this->getSEP08PostTransactionResponse($response);
        assert($sep08Response instanceof SEP08PostTransactionSuccess);
        assert($sep08Response->message === 'Tx approved');
        $responseTx = Transaction::fromEnvelopeBase64XdrString($sep08Response->tx);
        assert(count($responseTx->getSignatures()) === 2);
    }

    public function testRevised(): void
    {
        $integration = new RegulatedAssetsIntegration();
        $sep08Service = new Sep08Service($integration);
        $txBuilder = new TransactionBuilder($this->sourceAccount);
        /*
         * Operation 1: AllowTrust op where issuer fully authorizes account A, asset X
         * Operation 2: AllowTrust op where issuer fully authorizes account B, asset X
         * Operation 3: Payment from A to B
         * (missing) Operation 4 (not set for this test): AllowTrust op where issuer fully deauthorizes account B, asset X
         * (missing) Operation 5 (not set for this test): AllowTrust op where issuer fully deauthorizes account A, asset X
         *
         */
        $allowTrustAOp = (
            new AllowTrustOperationBuilder(
                trustor: $this->sourceAccount->getAccountId(),
                assetCode: $this->regulatedAssetCode,
                authorized: true,
                authorizedToMaintainLiabilities: false,
            )
        )->setSourceAccount($this->regulatedAssetIssuer)->build();

        $allowTrustBOp = (
            new AllowTrustOperationBuilder(
                trustor: $this->destinationAccountId,
                assetCode: $this->regulatedAssetCode,
                authorized:true,
                authorizedToMaintainLiabilities: false,
            )
        )->setSourceAccount($this->regulatedAssetIssuer)->build();

        $txBuilder->addOperation($allowTrustAOp);
        $txBuilder->addOperation($allowTrustBOp);
        $txBuilder->addOperation($this->paymentOperation);

        $tx = $txBuilder->build();
        $tx->sign($this->senderKeyPair, Network::testnet());
        $txXdr = $tx->toEnvelopeXdrBase64();
        $request = $this->postServerRequest(
            ['tx' => $txXdr],
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep08Service->handleRequest($request);
        assertEquals(200, $response->getStatusCode());
        $sep08Response = $this->getSEP08PostTransactionResponse($response);
        assert($sep08Response instanceof SEP08PostTransactionRevised);
        assert($sep08Response->message === 'Tx revised');
        $responseTx = Transaction::fromEnvelopeBase64XdrString($sep08Response->tx);
        assert($responseTx instanceof Transaction); // not fee bump
        assert(count($responseTx->getSignatures()) === 2);
        assert(count($responseTx->getOperations()) === 5);
        assertEquals($responseTx->getSequenceNumber(), $tx->getSequenceNumber());
    }

    public function testPending(): void
    {
        $integration = new RegulatedAssetsIntegration();
        $sep08Service = new Sep08Service($integration);
        $txBuilder = new TransactionBuilder($this->sourceAccount);
        $txBuilder->addOperation($this->paymentOperation);

        $tx = $txBuilder->build();
        $tx->sign($this->senderKeyPair, Network::testnet());
        $txXdr = $tx->toEnvelopeXdrBase64();
        $request = $this->postServerRequest(
            ['tx' => $txXdr],
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep08Service->handleRequest($request);
        assertEquals(200, $response->getStatusCode());
        $sep08Response = $this->getSEP08PostTransactionResponse($response);
        assert($sep08Response instanceof SEP08PostTransactionPending);
        assert($sep08Response->message === 'Tx pending');
        assert($sep08Response->timeout === 100);
    }

    public function testRejected(): void
    {
        $integration = new RegulatedAssetsIntegration();
        $sep08Service = new Sep08Service($integration);
        $txBuilder = new TransactionBuilder($this->sourceAccount);
        $txBuilder->addOperation($this->paymentOperation);

        $tx = $txBuilder->build();
        $tx->sign($this->senderKeyPair, Network::testnet());

        $feeAccountKp = KeyPair::random();
        $feeBumpTx = (new FeeBumpTransactionBuilder($tx))
            ->setFeeAccount($feeAccountKp->getAccountId())->setBaseFee(300)
            ->build();
        $feeBumpTx->sign($feeAccountKp, Network::testnet());

        $txXdr = $feeBumpTx->toEnvelopeXdrBase64();
        $request = $this->postServerRequest(
            ['tx' => $txXdr],
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep08Service->handleRequest($request);
        assertEquals(400, $response->getStatusCode());
        $sep08Response = $this->getSEP08PostTransactionResponse($response);
        assert($sep08Response instanceof SEP08PostTransactionRejected);
        assert($sep08Response->error === 'Unsupported transaction type');
    }

    public function testActionRequired(): void
    {
        $integration = new RegulatedAssetsIntegration();
        $sep08Service = new Sep08Service($integration);
        $txBuilder = new TransactionBuilder($this->sourceAccount);
        $txBuilder->addOperation($this->paymentOperation);
        $txBuilder->addOperation($this->paymentOperation);

        $tx = $txBuilder->build();
        $tx->sign($this->senderKeyPair, Network::testnet());
        $txXdr = $tx->toEnvelopeXdrBase64();
        $request = $this->postServerRequest(
            ['tx' => $txXdr],
            ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
        $response = $sep08Service->handleRequest($request);
        assertEquals(200, $response->getStatusCode());
        $sep08Response = $this->getSEP08PostTransactionResponse($response);
        assert($sep08Response instanceof SEP08PostTransactionActionRequired);

        assertEquals('Tx action required', $sep08Response->message);
        assertEquals('https://test.com/sep08/tx_action', $sep08Response->actionUrl);
        assertEquals('POST', $sep08Response->actionMethod);
        assertEquals(['last_name', 'email_address'], $sep08Response->actionFields);
    }

    private function getSEP08PostTransactionResponse(ResponseInterface $response): SEP08PostTransactionResponse
    {
        $statusCode = $response->getStatusCode();
        assertTrue($statusCode === 200 || $statusCode === 400);
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP08PostTransactionResponse::fromJson($decoded);
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

    /**
     * @param array<string, mixed> $parameters
     */
    private function postServerRequest(
        array $parameters,
        string $contentType,
    ): ServerRequestInterface {
        return ServerRequestBuilder::serverRequest('POST', $parameters, $this->approveEndpoint, $contentType);
    }
}
