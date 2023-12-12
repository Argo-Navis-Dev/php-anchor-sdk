<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep10\SEP10Service;
use ArgoNavis\PhpAnchorSdk\exception\InvalidConfig;
use ArgoNavis\Test\PhpAnchorSdk\config\AppConfig;
use ArgoNavis\Test\PhpAnchorSdk\config\Sep10Config;
use GuzzleHttp\Client;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\Uri;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\Xdr\XdrBuffer;
use Soneso\StellarSDK\Xdr\XdrEnvelopeType;
use Soneso\StellarSDK\Xdr\XdrTransactionEnvelope;

use function PHPUnit\Framework\assertEquals;
use function base64_decode;
use function count;
use function fopen;
use function json_decode;
use function json_encode;
use function sprintf;
use function urlencode;

class Sep10Test extends TestCase
{
    /**
     * @throws InvalidConfig
     */
    public function testGetChallenge(): void
    {
        $userAccountId = 'GDRIBLG67CHLGKKWFB3UWPHDESLAKRO4FIP5RR5VWXPPJV4LGBKHM3WM';
        $userSeed = 'SBA74A3EFR4YWNWAMWJO5DUNYB5WZT6KIK6FHZA4P7D5CASDT2JIZ4KF';
        //$clientAccountId = 'GB66AWTE5INBZKDHSWRC6DET6RY62TJVXMTDVWK3EW7MS55BJVRTQXXJ';
        //$clientSeed = 'SCPSMCBPDR6RNF2NYM5U6XOB3RZIHHRVZ6OURLMJGCWNKGSLCV2SC5CE';

        $appConfig = new AppConfig();
        $sep10Config = new Sep10Config();

        $data = ['account' => $userAccountId];
        $request = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri('http://localhost:8000/auth'))
            ->withQueryParams($data)
            ->withAddedHeader('Content-Type', 'application/json');

        $sep10Service = new SEP10Service($appConfig, $sep10Config);
        $response = $sep10Service->handleRequest($request, new Client());
        //print $response->getBody()->__toString();
        self::assertEquals(200, $response->getStatusCode());
        $json = @json_decode($response->getBody()->__toString(), true);
        self::assertIsArray($json);
        if (!isset($json['transaction'])) {
            self::fail('transaction not found');
        }
        $txEnvB64 = $json['transaction'];
        self::assertIsString($txEnvB64);
        $xdr = new XdrBuffer(base64_decode($txEnvB64));
        $envelopeXdr = XdrTransactionEnvelope::decode($xdr);
        assertEquals(XdrEnvelopeType::ENVELOPE_TYPE_TX, $envelopeXdr->getType()->getValue());
        $transaction = $envelopeXdr->getV1()?->getTx();
        self::assertNotNull($transaction);
        assertEquals('0', $transaction->getSequenceNumber()->getValue()->toString());
        $operations = $transaction->getOperations();
        self::assertGreaterThanOrEqual(2, count($operations));

        $tx = Transaction::fromEnvelopeBase64XdrString($txEnvB64);
        $tx->sign(KeyPair::fromSeed($userSeed), Network::testnet());
        $signedTx = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $signedTx];
        $jsonData = json_encode($data);
        self::assertIsString($jsonData);
        $stream = fopen(sprintf('data://text/plain,%s', urlencode($jsonData)), 'r');
        self::assertIsResource($stream);

        $request = (new ServerRequest())
            ->withMethod('POST')
            ->withUri(new Uri('http://localhost:8000/auth'))
            ->withAddedHeader('Content-Type', 'application/json')
            ->withBody(new Stream($stream));

        $response = $sep10Service->handleRequest($request, new Client());
        print $response->getBody()->__toString();
        self::assertEquals(200, $response->getStatusCode());
    }
}
