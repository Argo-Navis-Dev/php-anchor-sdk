<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Stellar\PaymentsHelper;
use ArgoNavis\PhpAnchorSdk\Stellar\ReceivedPayment;
use Soneso\StellarSDK\AssetTypeCreditAlphanum12;
use Soneso\StellarSDK\AssetTypeCreditAlphanum4;
use Soneso\StellarSDK\ChangeTrustOperationBuilder;
use Soneso\StellarSDK\CreateAccountOperationBuilder;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\FeeBumpTransactionBuilder;
use Soneso\StellarSDK\ManageSellOfferOperationBuilder;
use Soneso\StellarSDK\Memo;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\PathPaymentStrictReceiveOperationBuilder;
use Soneso\StellarSDK\PathPaymentStrictSendOperationBuilder;
use Soneso\StellarSDK\PaymentOperationBuilder;
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\Util\FriendBot;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertTrue;
use function assert;
use function count;
use function error_reporting;

use const E_ALL;

class StellarPaymentsQueryTest extends TestCase
{
    private string $issuerAccountId;
    private string $ginDistributionAccountId;
    private KeyPair $aliceKeyPair;
    private string $aliceAccountId;
    private KeyPair $bobKeyPair;
    private string $bobAccountId;
    private KeyPair $danaKeyPair;
    private string $danaAccountId;
    private string $ginAssetCode = 'GIN';
    private string $tonicAssetCode = 'TONIC';
    private string $wineAssetCode = 'WINE';
    private StellarSDK $sdk;
    private AssetTypeCreditAlphanum4 $ginAsset;
    private AssetTypeCreditAlphanum4 $wineAsset;
    private string $horizonUrl = 'https://horizon-testnet.stellar.org';

    public function setUp(): void
    {
        // Turn on error reporting
        error_reporting(E_ALL);
        $sdk = StellarSDK::getTestNetInstance();
        assert($sdk !== null);
        $this->sdk = $sdk;
        $issuerAccountKeyPair = KeyPair::random();
        $this->issuerAccountId = $issuerAccountKeyPair->getAccountId();
        $ginDistributionAccountKeyPair = KeyPair::random();
        $this->ginDistributionAccountId = $ginDistributionAccountKeyPair->getAccountId();
        $wineDistributionAccountKeyPair = KeyPair::random();
        $wineDistributionAccountId = $wineDistributionAccountKeyPair->getAccountId();
        $this->aliceKeyPair = KeyPair::random();
        $this->aliceAccountId = $this->aliceKeyPair->getAccountId();
        $this->bobKeyPair = KeyPair::random();
        $this->bobAccountId = $this->bobKeyPair->getAccountId();
        $gregKeyPair = KeyPair::random();
        $gregAccountId = $gregKeyPair->getAccountId();
        $this->danaKeyPair = KeyPair::random();
        $this->danaAccountId = $this->danaKeyPair->getAccountId();

        FriendBot::fundTestAccount($this->issuerAccountId);

        $this->ginAsset = new AssetTypeCreditAlphanum4($this->ginAssetCode, $this->issuerAccountId);
        $tonicAsset = new AssetTypeCreditAlphanum12($this->tonicAssetCode, $this->issuerAccountId);
        $this->wineAsset = new AssetTypeCreditAlphanum4($this->wineAssetCode, $this->issuerAccountId);

        $createGinDistributionAccountOp = (new CreateAccountOperationBuilder(
            $this->ginDistributionAccountId,
            '100',
        ))->build();
        $createWineDistributionAccountOp = (new CreateAccountOperationBuilder(
            $wineDistributionAccountId,
            '100',
        ))->build();
        $createAliceAccountOp = (new CreateAccountOperationBuilder($this->aliceAccountId, '100'))->build();
        $createBobAccountOp = (new CreateAccountOperationBuilder($this->bobAccountId, '100'))->build();
        $createGregAccountOp = (new CreateAccountOperationBuilder($gregAccountId, '100'))->build();
        $createDanaAccountOp = (new CreateAccountOperationBuilder($this->danaAccountId, '100'))->build();
        $ginDistTrustGinAssetOp = (new ChangeTrustOperationBuilder($this->ginAsset))
            ->setSourceAccount($this->ginDistributionAccountId)->build();
        $wineDistTrustWineAssetOp = (new ChangeTrustOperationBuilder($this->wineAsset))
            ->setSourceAccount($wineDistributionAccountId)->build();
        $aliceTrustGinAssetOp = (new ChangeTrustOperationBuilder($this->ginAsset))
            ->setSourceAccount($this->aliceAccountId)->build();
        $aliceTrustTonicAssetOp = (new ChangeTrustOperationBuilder($tonicAsset))
            ->setSourceAccount($this->aliceAccountId)->build();
        $bobTrustGinAssetOp = (new ChangeTrustOperationBuilder($this->ginAsset))
            ->setSourceAccount($this->bobAccountId)->build();
        $gregTrustTonicAssetOp = (new ChangeTrustOperationBuilder($tonicAsset))
            ->setSourceAccount($gregAccountId)->build();
        $gregTrustWineAssetOp = (new ChangeTrustOperationBuilder($this->wineAsset))
            ->setSourceAccount($gregAccountId)->build();
        $danaTrustWineAssetOp = (new ChangeTrustOperationBuilder($this->wineAsset))
            ->setSourceAccount($this->danaAccountId)->build();

        $fundGinDistWithGinAssetOp = (new PaymentOperationBuilder(
            $this->ginDistributionAccountId,
            $this->ginAsset,
            '100000',
        ))->build();

        $fundWineDistWithWineAssetOp = (new PaymentOperationBuilder(
            $wineDistributionAccountId,
            $this->wineAsset,
            '1000000',
        ))->build();

        $fundAliceWithGinAssetOp = (new PaymentOperationBuilder(
            $this->aliceAccountId,
            $this->ginAsset,
            '10000',
        ))->setSourceAccount($this->ginDistributionAccountId)->build();

        $fundAliceWithTonicAssetOp = (new PaymentOperationBuilder(
            $this->aliceAccountId,
            $tonicAsset,
            '10000',
        ))->build();

        $fundBobWithGinAssetOp = (new PaymentOperationBuilder(
            $this->bobAccountId,
            $this->ginAsset,
            '10000',
        ))->setSourceAccount($this->ginDistributionAccountId)->build();

        $fundGregWithTonicAssetOp = (new PaymentOperationBuilder(
            $gregAccountId,
            $tonicAsset,
            '10000',
        ))->build();

        $fundGregWithWineAssetOp = (new PaymentOperationBuilder(
            $gregAccountId,
            $this->wineAsset,
            '10000',
        ))->setSourceAccount($wineDistributionAccountId)->build();

        $fundDanaWithWineAssetOp = (new PaymentOperationBuilder(
            $this->danaAccountId,
            $this->wineAsset,
            '10000',
        ))->setSourceAccount($wineDistributionAccountId)->build();

        $aliceOfferOp = (new ManageSellOfferOperationBuilder(
            selling: $this->ginAsset,
            buying: $tonicAsset,
            amount: '500',
            price: '2',
        ))->setSourceAccount($this->aliceAccountId)->build();

        $gregOfferOp = (new ManageSellOfferOperationBuilder(
            selling: $tonicAsset,
            buying: $this->wineAsset,
            amount: '500',
            price: '2',
        ))->setSourceAccount($gregAccountId)->build();

        $issuerAccount = $this->sdk->requestAccount($this->issuerAccountId);
        $tx = (new TransactionBuilder(sourceAccount: $issuerAccount))
            ->addOperations([
                $createGinDistributionAccountOp,
                $createWineDistributionAccountOp,
                $createAliceAccountOp,
                $createBobAccountOp,
                $createGregAccountOp,
                $createDanaAccountOp,
                $ginDistTrustGinAssetOp,
                $fundGinDistWithGinAssetOp,
                $wineDistTrustWineAssetOp,
                $fundWineDistWithWineAssetOp,
                $aliceTrustGinAssetOp,
                $aliceTrustTonicAssetOp,
                $bobTrustGinAssetOp,
                $gregTrustTonicAssetOp,
                $gregTrustWineAssetOp,
                $danaTrustWineAssetOp,
                $fundAliceWithGinAssetOp,
                $fundAliceWithTonicAssetOp,
                $fundBobWithGinAssetOp,
                $fundGregWithTonicAssetOp,
                $fundGregWithWineAssetOp,
                $fundDanaWithWineAssetOp,
                $aliceOfferOp,
                $gregOfferOp,
            ])->build();
        $tx->sign($issuerAccountKeyPair, Network::testnet());
        $tx->sign($ginDistributionAccountKeyPair, Network::testnet());
        $tx->sign($wineDistributionAccountKeyPair, Network::testnet());
        $tx->sign($this->aliceKeyPair, Network::testnet());
        $tx->sign($this->bobKeyPair, Network::testnet());
        $tx->sign($gregKeyPair, Network::testnet());
        $tx->sign($this->danaKeyPair, Network::testnet());
        $response = $this->sdk->submitTransaction($tx);
        assertTrue($response->isSuccessful());
    }

    public function testPaymentsCheck(): void
    {
        $ginAssetCode = $this->ginAsset->getCode();
        $ginAssetIssuer = $this->ginAsset->getIssuer();

        $aliceAccount = $this->sdk->requestAccount($this->aliceAccountId);

        $paymentAlice = (new PaymentOperationBuilder(
            $this->ginDistributionAccountId,
            $this->ginAsset,
            '20',
        ))->build();

        $memo = Memo::id(10001);
        $tx = (new TransactionBuilder(sourceAccount: $aliceAccount))
            ->addOperations([$paymentAlice])->addMemo($memo)->build();

        $tx->sign($this->aliceKeyPair, Network::testnet());
        $response = $this->sdk->submitTransaction($tx);
        assertTrue($response->isSuccessful());

        $queryResult = PaymentsHelper::queryReceivedPayments(
            horizonUrl: $this->horizonUrl,
            receiverAccountId: $this->ginDistributionAccountId,
        );
        $receivedPayments = $queryResult->receivedPayments;
        assertCount(2, $receivedPayments); // the first one is from account funding
        $receivedPayment = $receivedPayments[1];
        $this->assertPaymentMatch(
            receivedPayment: $receivedPayment,
            senderAccountId: $this->aliceAccountId,
            receiverAccountId: $this->ginDistributionAccountId,
            amountInAsDecimalString: '20.0000000',
            memoValue: '10001',
            memoType: 'id',
            assetCode: $ginAssetCode,
            assetIssuer: $ginAssetIssuer,
        );

        $lastPagingToken = $queryResult->lastTransactionPagingToken;

        $queryResult = PaymentsHelper::queryReceivedPayments(
            horizonUrl: $this->horizonUrl,
            receiverAccountId: $this->ginDistributionAccountId,
            cursor: $lastPagingToken,
        );

        assertCount(0, $queryResult->receivedPayments);

        // alice and bob send in same tx
        $paymentAlice = (new PaymentOperationBuilder(
            $this->ginDistributionAccountId,
            $this->ginAsset,
            '5',
        ))->build();

        $paymentBob = (new PaymentOperationBuilder(
            $this->ginDistributionAccountId,
            $this->ginAsset,
            '25',
        ))->setSourceAccount($this->bobAccountId)->build();

        $memo = Memo::id(10002);
        $tx = (new TransactionBuilder(sourceAccount: $aliceAccount))
            ->addOperations([$paymentAlice, $paymentBob])->addMemo($memo)->build();

        $tx->sign($this->aliceKeyPair, Network::testnet());
        $tx->sign($this->bobKeyPair, Network::testnet());

        $response = $this->sdk->submitTransaction($tx);
        assertTrue($response->isSuccessful());

        $queryResult = PaymentsHelper::queryReceivedPayments(
            horizonUrl: $this->horizonUrl,
            receiverAccountId: $this->ginDistributionAccountId,
            cursor: $lastPagingToken,
        );
        $receivedPayments = $queryResult->receivedPayments;
        assertCount(2, $receivedPayments);

        $aliceFound = false;
        $bobFound = false;
        foreach ($receivedPayments as $receivedPayment) {
            if ($receivedPayment->senderAccountId === $this->bobAccountId) {
                $bobFound = true;
                $this->assertPaymentMatch(
                    receivedPayment: $receivedPayment,
                    senderAccountId: $this->bobAccountId,
                    receiverAccountId: $this->ginDistributionAccountId,
                    amountInAsDecimalString: '25.0000000',
                    memoValue: '10002',
                    memoType: 'id',
                    assetCode: $ginAssetCode,
                    assetIssuer: $ginAssetIssuer,
                );
            } elseif ($receivedPayment->senderAccountId === $this->aliceAccountId) {
                $aliceFound = true;
                $this->assertPaymentMatch(
                    receivedPayment: $receivedPayment,
                    senderAccountId: $this->aliceAccountId,
                    receiverAccountId: $this->ginDistributionAccountId,
                    amountInAsDecimalString: '5.0000000',
                    memoValue: '10002',
                    memoType: 'id',
                    assetCode: $ginAssetCode,
                    assetIssuer: $ginAssetIssuer,
                );
            }
        }
        assertTrue($aliceFound);
        assertTrue($bobFound);
        $lastPagingToken = $queryResult->lastTransactionPagingToken;

        // alice and bob send in different tx
        $paymentAlice = (new PaymentOperationBuilder(
            $this->ginDistributionAccountId,
            $this->ginAsset,
            '8',
        ))->build();

        $memo = Memo::id(10003);
        $tx = (new TransactionBuilder(sourceAccount: $aliceAccount))
            ->addOperation($paymentAlice)->addMemo($memo)->build();

        $tx->sign($this->aliceKeyPair, Network::testnet());

        $response = $this->sdk->submitTransaction($tx);
        assertTrue($response->isSuccessful());

        $bobAccount = $this->sdk->requestAccount($this->bobAccountId);
        $paymentBob = (new PaymentOperationBuilder(
            $this->ginDistributionAccountId,
            $this->ginAsset,
            '9',
        ))->build();

        $memo = Memo::id(10004);
        $tx = (new TransactionBuilder(sourceAccount: $bobAccount))
            ->addOperation($paymentBob)->addMemo($memo)->build();

        $tx->sign($this->bobKeyPair, Network::testnet());

        $response = $this->sdk->submitTransaction($tx);
        assertTrue($response->isSuccessful());

        $queryResult = PaymentsHelper::queryReceivedPayments(
            horizonUrl: $this->horizonUrl,
            receiverAccountId: $this->ginDistributionAccountId,
            cursor: $lastPagingToken,
        );
        $receivedPayments = $queryResult->receivedPayments;
        assertCount(2, $receivedPayments);

        $aliceFound = false;
        $bobFound = false;
        foreach ($receivedPayments as $receivedPayment) {
            if ($receivedPayment->senderAccountId === $this->bobAccountId) {
                $bobFound = true;
                $this->assertPaymentMatch(
                    receivedPayment: $receivedPayment,
                    senderAccountId: $this->bobAccountId,
                    receiverAccountId: $this->ginDistributionAccountId,
                    amountInAsDecimalString: '9.0000000',
                    memoValue: '10004',
                    memoType: 'id',
                    assetCode: $ginAssetCode,
                    assetIssuer: $ginAssetIssuer,
                );
            } elseif ($receivedPayment->senderAccountId === $this->aliceAccountId) {
                $aliceFound = true;
                $this->assertPaymentMatch(
                    receivedPayment: $receivedPayment,
                    senderAccountId: $this->aliceAccountId,
                    receiverAccountId: $this->ginDistributionAccountId,
                    amountInAsDecimalString: '8.0000000',
                    memoValue: '10003',
                    memoType: 'id',
                    assetCode: $ginAssetCode,
                    assetIssuer: $ginAssetIssuer,
                );
            }
        }
        assertTrue($aliceFound);
        assertTrue($bobFound);
        $lastPagingToken = $queryResult->lastTransactionPagingToken;

        //fee bump
        $paymentBob = (new PaymentOperationBuilder(
            $this->ginDistributionAccountId,
            $this->ginAsset,
            '15',
        ))->build();

        $memo = Memo::id(10005);
        $tx = (new TransactionBuilder(sourceAccount: $bobAccount))
            ->addOperation($paymentBob)->addMemo($memo)->build();

        $tx->sign($this->bobKeyPair, Network::testnet());

        $feeBump = (new FeeBumpTransactionBuilder(inner: $tx))
            ->setFeeAccount($this->aliceAccountId)->setBaseFee(100)->build();
        $feeBump->sign($this->aliceKeyPair, Network::testnet());
        $response = $this->sdk->submitTransaction($feeBump);
        assertTrue($response->isSuccessful());

        $queryResult = PaymentsHelper::queryReceivedPayments(
            horizonUrl: $this->horizonUrl,
            receiverAccountId: $this->ginDistributionAccountId,
            cursor: $lastPagingToken,
        );
        $receivedPayments = $queryResult->receivedPayments;
        assertCount(1, $receivedPayments);
        $this->assertPaymentMatch(
            receivedPayment: $receivedPayments[0],
            senderAccountId: $this->bobAccountId,
            receiverAccountId: $this->ginDistributionAccountId,
            amountInAsDecimalString: '15.0000000',
            memoValue: '10005',
            memoType: 'id',
            assetCode: $ginAssetCode,
            assetIssuer: $ginAssetIssuer,
        );

        $strictSendPaths = $this->sdk->findStrictSendPaths()
            ->forSourceAsset($this->wineAsset)
            ->forSourceAmount('12')
            ->forDestinationAccount($this->ginDistributionAccountId)
            ->execute();
        $pathArr = $strictSendPaths->getPaths()->toArray();
        $this->assertTrue(count($pathArr) > 0);
        $path = $pathArr[0];

        // path payments
        $strictSendOp = (new PathPaymentStrictSendOperationBuilder(
            sendAsset: $this->wineAsset,
            sendAmount: '12',
            destinationAccountId: $this->ginDistributionAccountId,
            destAsset: $this->ginAsset,
            destMin: '3',
        ))->setPath($path->getPath()->toArray())->build();

        $danaAccount = $this->sdk->requestAccount($this->danaAccountId);
        $memo = Memo::id(10015);
        $transaction = (new TransactionBuilder($danaAccount))->addOperation($strictSendOp)->addMemo($memo)->build();
        $transaction->sign($this->danaKeyPair, Network::testnet());
        $response = $this->sdk->submitTransaction($transaction);
        $this->assertTrue($response->isSuccessful());

        $queryResult = PaymentsHelper::queryReceivedPayments(
            horizonUrl: $this->horizonUrl,
            receiverAccountId: $this->ginDistributionAccountId,
        );
        $receivedPayments = $queryResult->receivedPayments;
        assertTrue(count($receivedPayments) > 1);
        $found = false;
        foreach ($receivedPayments as $receivedPayment) {
            if ($receivedPayment->memoValue === '10015') {
                $found = true;
                $this->assertPaymentMatch(
                    receivedPayment: $receivedPayment,
                    senderAccountId: $this->danaAccountId,
                    receiverAccountId: $this->ginDistributionAccountId,
                    amountInAsDecimalString: '3.0000000',
                    memoValue: '10015',
                    memoType: 'id',
                    assetCode: $ginAssetCode,
                    assetIssuer: $ginAssetIssuer,
                );

                break;
            }
        }

        assertTrue($found);
        $lastPagingToken = $queryResult->lastTransactionPagingToken;

        $strictReceivePaths = $this->sdk->findStrictReceivePaths()
            ->forDestinationAsset($this->ginAsset)
            ->forDestinationAmount('8')
            ->forSourceAssets([$this->wineAsset])
            ->execute();
        $pathArr = $strictReceivePaths->getPaths()->toArray();
        $this->assertTrue(count($pathArr) > 0);
        $path = $pathArr[0];
        $strictReceiveOp = (new PathPaymentStrictReceiveOperationBuilder(
            sendAsset: $this->wineAsset,
            sendMax: '32',
            destinationAccountId: $this->ginDistributionAccountId,
            destAsset: $this->ginAsset,
            destAmount: '8',
        ))->setPath($path->getPath()->toArray())->build();

        $memo = Memo::id(10016);
        $transaction = (new TransactionBuilder($danaAccount))->addOperation($strictReceiveOp)->addMemo($memo)->build();
        $transaction->sign($this->danaKeyPair, Network::testnet());
        $response = $this->sdk->submitTransaction($transaction);
        $this->assertTrue($response->isSuccessful());

        $queryResult = PaymentsHelper::queryReceivedPayments(
            horizonUrl: $this->horizonUrl,
            receiverAccountId: $this->ginDistributionAccountId,
            cursor: $lastPagingToken,
        );
        $receivedPayments = $queryResult->receivedPayments;
        assertCount(1, $receivedPayments);
        $this->assertPaymentMatch(
            receivedPayment: $receivedPayments[0],
            senderAccountId: $this->danaAccountId,
            receiverAccountId: $this->ginDistributionAccountId,
            amountInAsDecimalString: '8.0000000',
            memoValue: '10016',
            memoType: 'id',
            assetCode: $ginAssetCode,
            assetIssuer: $ginAssetIssuer,
        );
    }

    private function assertPaymentMatch(
        ReceivedPayment $receivedPayment,
        string $senderAccountId,
        string $receiverAccountId,
        string $amountInAsDecimalString,
        ?string $memoValue = null,
        ?string $memoType = null,
        ?string $assetCode = null,
        ?string $assetIssuer = null,
    ): void {
        assertEquals($senderAccountId, $receivedPayment->senderAccountId);
        assertEquals($receiverAccountId, $receivedPayment->receiverAccountId);
        assertEquals($amountInAsDecimalString, $receivedPayment->amountInAsDecimalString);
        assertEquals($memoValue, $receivedPayment->memoValue);
        assertEquals($memoType, $receivedPayment->memoType);
        assertEquals($assetCode, $receivedPayment->assetCode);
        assertEquals($assetIssuer, $receivedPayment->assetIssuer);
    }
}
