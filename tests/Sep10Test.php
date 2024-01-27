<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Service;
use ArgoNavis\PhpAnchorSdk\exception\InvalidConfig;
use ArgoNavis\Test\PhpAnchorSdk\config\AppConfig;
use ArgoNavis\Test\PhpAnchorSdk\config\Sep10Config;
use DateTime;
use Exception;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\Uri;
use Psr\Http\Message\ResponseInterface;
use Soneso\StellarSDK\AbstractTransaction;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\CreateAccountOperationBuilder;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\FeeBumpTransactionBuilder;
use Soneso\StellarSDK\ManageDataOperationBuilder;
use Soneso\StellarSDK\Memo;
use Soneso\StellarSDK\MuxedAccount;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\SetOptionsOperationBuilder;
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\TimeBounds;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\Util\FriendBot;
use Soneso\StellarSDK\Xdr\XdrBuffer;
use Soneso\StellarSDK\Xdr\XdrDecoratedSignature;
use Soneso\StellarSDK\Xdr\XdrEnvelopeType;
use Soneso\StellarSDK\Xdr\XdrMemoType;
use Soneso\StellarSDK\Xdr\XdrOperation;
use Soneso\StellarSDK\Xdr\XdrOperationType;
use Soneso\StellarSDK\Xdr\XdrSignerKey;
use Soneso\StellarSDK\Xdr\XdrSignerKeyType;
use Soneso\StellarSDK\Xdr\XdrTransactionEnvelope;
use UnexpectedValueException;
use phpseclib3\Math\BigInteger;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertGreaterThanOrEqual;
use function PHPUnit\Framework\assertNotEquals;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertTrue;
use function assert;
use function base64_decode;
use function base64_encode;
use function count;
use function fopen;
use function intval;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function microtime;
use function random_bytes;
use function round;
use function sprintf;
use function str_starts_with;
use function strval;
use function urlencode;

class Sep10Test extends TestCase
{
    /**
     * @throws InvalidConfig
     */
    public function testGetChallenge(): void
    {
        $userKeyPair = KeyPair::random();
        $userAccountId = $userKeyPair->getAccountId();

        $appConfig = new AppConfig();
        $sep10Config = new Sep10Config();
        $sep10Config->homeDomains = ['localhost:8000'];
        $sep10Config->sep10SigningSeed = 'SCYJJBZTHTN2RZI7UA2MN3RNMSDNQ3BKHPYWXXPXMRJ4KLU7N5XQ5BXE';
        //GA4A5CVA2QJNS5CBPOEFKWJC4F5SUI36IPWHAKIEKBQ7UVGJ4Y5WC5FA

        $data = ['account' => $userAccountId];
        $request = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri('http://localhost:8000/auth'))
            ->withQueryParams($data)
            ->withAddedHeader('Content-Type', 'application/json');

        $sep10Service = new Sep10Service($appConfig, $sep10Config);
        $response = $sep10Service->handleRequest($request, new Client());
        //print $response->getBody()->__toString();
        self::assertEquals(200, $response->getStatusCode());
        $json = @json_decode($response->getBody()->__toString(), true);
        self::assertIsArray($json);
        assert(isset($json['transaction']));
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
        $tx->sign($userKeyPair, Network::testnet());
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
        self::assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));
        $jwt = $decoded['token'];
        assert(is_string($jwt));
    }

    public function testInvalidChallengeRequest(): void
    {
        $appConfig = new AppConfig();
        $sep10Config = new Sep10Config();

        $data = [];
        $request = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri('http://localhost:8000/auth'))
            ->withQueryParams($data)
            ->withAddedHeader('Content-Type', 'application/json');

        $sep10Service = null;
        $thrown = false;
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertTrue($thrown);
        self::assertNull($sep10Service);

        $thrown = false;
        $sep10Config->homeDomains = ['localhost:8000'];
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertTrue($thrown);
        self::assertNull($sep10Service);

        $sep10Config->sep10SigningSeed = 'SCYJJBZTHTN2RZI7UA2MN3RNMSDNQ3BKHPYWXXPXMRJ4KLU7N5XQ5BXE';
        $thrown = false;
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertFalse($thrown);
        self::assertNotNull($sep10Service);
        // no account in query parameters
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        $data = ['account' => 123];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        $userAccountId = 'GDRIBLG67CHLGKKWFB3UWPHDESLAKRO4FIP5RR5VWXPPJV4LGBKHM3WM';
        $data = ['account' => $userAccountId, 'memo' => 123];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        $data = ['account' => $userAccountId, 'memo' => '123', 'home_domain' => 123];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        $data = ['account' => $userAccountId, 'memo' => '123', 'home_domain' => 'home.com', 'client_domain' => 123];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        $data = ['account' => $userAccountId, 'memo' => '123',
            'home_domain' => 'home.com', 'client_domain' => 'client.com',
        ];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode()); // home domain not in list
        $data = ['account' => 'invalid'];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode()); // invalid account
        self::assertEquals('{"error":"client wallet account invalid is invalid"}', $response->getBody()->__toString());

        $sep10Config->custodialAccountList = [$userAccountId];
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertFalse($thrown);
        $data = ['account' => $userAccountId, 'client_domain' => 'client.com'];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals('{"error":"client_domain must not be specified if ' .
            'the account is an custodial-wallet account"}', $response->getBody()->__toString());

        $sep10Config->custodialAccountList = null;
        $sep10Config->clientAttributionRequired = true;
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertFalse($thrown);
        $data = ['account' => $userAccountId];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals('{"error":"client_domain is required"}', $response->getBody()->__toString());

        $sep10Config->allowedClientDomains = ['client.com'];
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertFalse($thrown);
        // client_domain provided is not in the configured allow list
        $data = ['account' => $userAccountId, 'client_domain' => 'not-allowed.com'];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(403, $response->getStatusCode());
        self::assertEquals('{"error":"unable to process"}', $response->getBody()->__toString());

        $sep10Config->clientAttributionRequired = false;
        $sep10Config->allowedClientDomains = null;
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertFalse($thrown);
        $data = ['account' => 'MB5XLN4AFD5USFOUMVQRSSHK2PQLOAQ57D4TL4OVFARMDBMNAZ2LWAAAAAAAAAAAPNDB6', 'memo' => '123'];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals('{"error":"memo not allowed for muxed accounts"}', $response->getBody()->__toString());

        $data = ['account' => $userAccountId, 'memo' => 'hello'];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals('{"error":"invalid memo value: hello"}', $response->getBody()->__toString());

        $mock = new MockHandler([
            new Response(404, ['X-Foo' => 'Bar'], 'not found'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $data = ['account' => $userAccountId, 'client_domain' => 'hello.com'];
        $request = $request->withQueryParams($data);
        $response = $sep10Service->handleRequest($request, $client);
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals('{"error":"client signing key not found for domain hello.com : ' .
            'Stellar toml not found. Response status code 404"}', $response->getBody()->__toString());

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], 'hello! I am not parsable'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $response = $sep10Service->handleRequest($request, $client);
        self::assertEquals(400, $response->getStatusCode());

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], 'VERSION="2.0.0"'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $response = $sep10Service->handleRequest($request, $client);
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals('{"error":"client signing key not found ' .
            'for domain hello.com"}', $response->getBody()->__toString());
    }

    public function testChallengeResponse(): void
    {
        $userAccountId = 'GDRIBLG67CHLGKKWFB3UWPHDESLAKRO4FIP5RR5VWXPPJV4LGBKHM3WM';

        $appConfig = new AppConfig();
        $sep10Config = new Sep10Config();
        $sep10Config->homeDomains = ['localhost:8000'];
        $sep10Config->sep10SigningSeed = 'SCYJJBZTHTN2RZI7UA2MN3RNMSDNQ3BKHPYWXXPXMRJ4KLU7N5XQ5BXE';
        $serverSigningKey = 'GA4A5CVA2QJNS5CBPOEFKWJC4F5SUI36IPWHAKIEKBQ7UVGJ4Y5WC5FA';
        $webAuthHost = 'localhost:8000';
        $webAuthEndpoint = 'http://' . $webAuthHost . '/auth';

        $sep10Service = null;
        $thrown = false;
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertFalse($thrown);
        self::assertNotNull($sep10Service);

        $data = ['account' => $userAccountId];
        $request = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri($webAuthEndpoint))
            ->withQueryParams($data)
            ->withAddedHeader('Content-Type', 'application/json');

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(200, $response->getStatusCode());
        $txB64Xdr = $this->getTransactionFromResponse($response);
        $this->validateTransaction(
            $txB64Xdr,
            $userAccountId,
            $serverSigningKey,
            $sep10Config->homeDomains[0],
            $webAuthHost,
            300,
            Network::testnet(),
        );

        $data = ['account' => $userAccountId, 'memo' => '1234'];
        $response = $sep10Service->handleRequest($request->withQueryParams($data), new Client());
        self::assertEquals(200, $response->getStatusCode());
        $txB64Xdr = $this->getTransactionFromResponse($response);

        $this->validateTransaction(
            $txB64Xdr,
            $userAccountId,
            $serverSigningKey,
            $sep10Config->homeDomains[0],
            $webAuthHost,
            100,
            Network::testnet(),
            memo: 1234,
        );

        $data = ['account' => $userAccountId, 'memo' => '1234', 'client_domain' => 'client-domain.org'];
        $clientAccountId = 'GB66AWTE5INBZKDHSWRC6DET6RY62TJVXMTDVWK3EW7MS55BJVRTQXXJ';
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], 'SIGNING_KEY="' . $clientAccountId . '"'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $response = $sep10Service->handleRequest($request->withQueryParams($data), $client);
        self::assertEquals(200, $response->getStatusCode());
        $txB64Xdr = $this->getTransactionFromResponse($response);

        $this->validateTransaction(
            $txB64Xdr,
            $userAccountId,
            $serverSigningKey,
            $sep10Config->homeDomains[0],
            $webAuthHost,
            100,
            Network::testnet(),
            clientDomainAccountId: $clientAccountId,
            memo: 1234,
        );
    }

    /**
     * @throws Exception
     */
    public function testPostedChallengeValidation(): void
    {
        $userKeyPair = KeyPair::random();
        $userAccountId = $userKeyPair->getAccountId();

        $appConfig = new AppConfig();
        $sep10Config = new Sep10Config();
        $sep10Config->homeDomains = ['localhost:8000'];
        $sep10Config->sep10SigningSeed = 'SCYJJBZTHTN2RZI7UA2MN3RNMSDNQ3BKHPYWXXPXMRJ4KLU7N5XQ5BXE';
        $serverSigningKey = 'GA4A5CVA2QJNS5CBPOEFKWJC4F5SUI36IPWHAKIEKBQ7UVGJ4Y5WC5FA';
        $webAuthHost = 'localhost:8000';
        $webAuthEndpoint = 'http://' . $webAuthHost . '/auth';
        $clientAccountId = 'GB66AWTE5INBZKDHSWRC6DET6RY62TJVXMTDVWK3EW7MS55BJVRTQXXJ';
        $clientSeed = 'SCPSMCBPDR6RNF2NYM5U6XOB3RZIHHRVZ6OURLMJGCWNKGSLCV2SC5CE';

        $sep10Service = null;
        $thrown = false;
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertFalse($thrown);
        self::assertNotNull($sep10Service);

        $data = ['account' => $userAccountId];
        $request = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri($webAuthEndpoint))
            ->withQueryParams($data)
            ->withAddedHeader('Content-Type', 'application/json');

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(200, $response->getStatusCode());
        $txB64Xdr = $this->getTransactionFromResponse($response);

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        assert($tx instanceof Transaction);
        $tx->sign($userKeyPair, Network::testnet());
        $txB64Xdr = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $txB64Xdr];
        $request = (new ServerRequest())
            ->withMethod('POST')
            ->withUri(new Uri($webAuthEndpoint))
            ->withBody($this->getStreamFromDataArray($data))
            ->withAddedHeader('Content-Type', 'application/json');

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(200, $response->getStatusCode());

        $data = ['transaction' => '123'];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());

        $data = [];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());

        $data = ['transaction' => 123];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());

        $payerKeyPair = KeyPair::random();
        $feeBump = (new FeeBumpTransactionBuilder($tx))->setBaseFee(200)
            ->setFeeAccount($payerKeyPair->getAccountId())->build();
        $feeBump->sign($payerKeyPair, Network::testnet());
        $data = ['transaction' => $feeBump->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Transaction cannot be a fee bump transaction"}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($payerKeyPair->getAccountId(), new BigInteger(-1));
        $tx1 = (new TransactionBuilder($sourceAccount))->addOperation(
            (new CreateAccountOperationBuilder($payerKeyPair->getAccountId(), '10'))->build(),
        )->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Transaction source account is not equal to server account."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(10));
        $tx1 = (new TransactionBuilder($sourceAccount))->addOperation(
            (new CreateAccountOperationBuilder($payerKeyPair->getAccountId(), '10'))->build(),
        )->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. The transaction sequence number should be zero."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $tx1 = (new TransactionBuilder($sourceAccount))->addMemo(Memo::text('jim'))->addOperation(
            (new CreateAccountOperationBuilder($payerKeyPair->getAccountId(), '10'))->build(),
        )->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Only memo type `id` is supported"}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $tx1 = (new TransactionBuilder($sourceAccount))->addOperation(
            (new CreateAccountOperationBuilder($payerKeyPair->getAccountId(), '10'))->build(),
        )->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Transaction requires timebounds"}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $minTime = new DateTime('now');
        $minTime->modify('+10 hours');
        $maxTime = new DateTime('now');
        $maxTime->modify('+12 hours');
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation(
                (new CreateAccountOperationBuilder($payerKeyPair->getAccountId(), '10'))->build(),
            )->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Transaction is not within range of the specified timebounds."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $minTime = new DateTime('now');
        $minTime->modify('-5 seconds');
        $maxTime = new DateTime('now');
        $maxTime->modify('+5 seconds');
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation(
                (new CreateAccountOperationBuilder($payerKeyPair->getAccountId(), '10'))->build(),
            )->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Operation type should be ManageData."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $nonce = random_bytes(48);
        $builder = new ManageDataOperationBuilder($webAuthHost . ' auth', base64_encode($nonce));
        $domainNameOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Operation must have a source account."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $nonce = random_bytes(48);
        $builder = new ManageDataOperationBuilder('no good' . ' auth', base64_encode($nonce));
        $mUserAccount = MuxedAccount::fromAccountId($userAccountId);
        $builder->setMuxedSourceAccount($mUserAccount);
        $domainNameOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. The transaction operation key name does ' .
            'not include one of the expected home domains."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $builder = new ManageDataOperationBuilder($webAuthHost . ' auth', null);
        $builder->setMuxedSourceAccount($mUserAccount);
        $domainNameOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. The transaction operation value should not be null."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $nonce = random_bytes(12);
        $builder = new ManageDataOperationBuilder($webAuthHost . ' auth', base64_encode($nonce));
        $builder->setMuxedSourceAccount($mUserAccount);
        $domainNameOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Random nonce encoded as base64 should be 64 bytes long."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $nonce = random_bytes(48);
        $builder = new ManageDataOperationBuilder($webAuthHost . ' auth', base64_encode($nonce));
        $builder->setMuxedSourceAccount($mUserAccount);
        $domainNameOperation = $builder->build();
        $builder = new ManageDataOperationBuilder('web_auth_domain', $webAuthHost);
        $webAuthDomainOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Operation should have a source account."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $builder = new ManageDataOperationBuilder('web_auth_domain', $webAuthHost);
        $builder->setMuxedSourceAccount($mUserAccount);
        $webAuthDomainOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Subsequent operations are unrecognized."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $builder = new ManageDataOperationBuilder('web_auth_domain', null);
        $builder->setSourceAccount($serverSigningKey);
        $webAuthDomainOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. web_auth_domain operation value should not be null."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $builder = new ManageDataOperationBuilder('web_auth_domain', 'ginger beer');
        $builder->setSourceAccount($serverSigningKey);
        $webAuthDomainOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. web_auth_domain operation value does not match localhost:8000"}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $builder = new ManageDataOperationBuilder('web_auth_domain', $webAuthHost);
        $builder->setSourceAccount($serverSigningKey);
        $webAuthDomainOperation = $builder->build();
        $builder = new ManageDataOperationBuilder('client_domain', 'client.org');
        $clientDomainOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)
            ->addOperation($clientDomainOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Operation should have a source account."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $builder = new ManageDataOperationBuilder('client_domain', null);
        $builder->setSourceAccount($clientAccountId);
        $clientDomainOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)
            ->addOperation($clientDomainOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. client_domain operation value should not be null."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $builder = new ManageDataOperationBuilder('client_domain', 'client.org');
        $builder->setSourceAccount($clientAccountId);
        $clientDomainOperation = $builder->build();
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)
            ->addOperation($clientDomainOperation)->build();
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Transaction has no signatures."}',
            $response->getBody()->__toString(),
        );

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)
            ->addOperation($clientDomainOperation)->build();
        $tx1->sign($userKeyPair, Network::testnet());
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Transaction not signed by server:' .
            ' GA4A5CVA2QJNS5CBPOEFKWJC4F5SUI36IPWHAKIEKBQ7UVGJ4Y5WC5FA"}',
            $response->getBody()->__toString(),
        );

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], 'SIGNING_KEY="' . $clientAccountId . '"'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $serverKeyPair = KeyPair::fromSeed($sep10Config->sep10SigningSeed);
        $clientDomainKeyPair = KeyPair::fromSeed($clientSeed);
        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)
            ->addOperation($clientDomainOperation)->build();
        $tx1->sign($serverKeyPair, Network::testnet());
        $tx1->sign($userKeyPair, Network::testnet());
        $tx1->sign($clientDomainKeyPair, Network::testnet());
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, $client);
        self::assertEquals(200, $response->getStatusCode());

        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)
            ->addOperation($clientDomainOperation)->build();
        $tx1->sign($userKeyPair, Network::testnet());
        $tx1->sign($clientDomainKeyPair, Network::testnet());
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, $client);
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Transaction not signed by server:' .
            ' GA4A5CVA2QJNS5CBPOEFKWJC4F5SUI36IPWHAKIEKBQ7UVGJ4Y5WC5FA"}',
            $response->getBody()->__toString(),
        );

        FriendBot::fundTestAccount($userKeyPair->getAccountId());
        $sourceAccount = new Account($serverSigningKey, new BigInteger(-1));
        $tx1 = (new TransactionBuilder($sourceAccount))->setTimeBounds(new TimeBounds($minTime, $maxTime))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)
            ->addOperation($clientDomainOperation)->build();
        //$tx1->sign($serverKeyPair, Network::testnet());
        $tx1->sign($userKeyPair, Network::testnet());
        $tx1->sign($clientDomainKeyPair, Network::testnet());
        $data = ['transaction' => $tx1->toEnvelopeXdrBase64()];
        $request = $request->withBody($this->getStreamFromDataArray($data));
        $response = $sep10Service->handleRequest($request, $client);
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Transaction not signed by server:' .
            ' GA4A5CVA2QJNS5CBPOEFKWJC4F5SUI36IPWHAKIEKBQ7UVGJ4Y5WC5FA"}',
            $response->getBody()->__toString(),
        );

        //print $response->getBody()->__toString();
    }

    public function testSignatures(): void
    {
        $userKeyPair = KeyPair::random();
        $userAccountId = $userKeyPair->getAccountId();

        $appConfig = new AppConfig();
        $sep10Config = new Sep10Config();
        $sep10Config->homeDomains = ['localhost:8000'];
        $sep10Config->sep10SigningSeed = 'SCYJJBZTHTN2RZI7UA2MN3RNMSDNQ3BKHPYWXXPXMRJ4KLU7N5XQ5BXE';
        $webAuthHost = 'localhost:8000';
        $webAuthEndpoint = 'http://' . $webAuthHost . '/auth';
        $clientDomainAccountId = 'GB66AWTE5INBZKDHSWRC6DET6RY62TJVXMTDVWK3EW7MS55BJVRTQXXJ';
        $clientDomainSeed = 'SCPSMCBPDR6RNF2NYM5U6XOB3RZIHHRVZ6OURLMJGCWNKGSLCV2SC5CE';
        $clientDomainKeyPair = KeyPair::fromSeed($clientDomainSeed);

        $sep10Service = null;
        $thrown = false;
        try {
            $sep10Service = new Sep10Service($appConfig, $sep10Config);
        } catch (InvalidConfig) {
            $thrown = true;
        }
        self::assertFalse($thrown);
        self::assertNotNull($sep10Service);

        $data = ['account' => $userAccountId];
        $getRequest = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri($webAuthEndpoint))
            ->withQueryParams($data)
            ->withAddedHeader('Content-Type', 'application/json');

        $response = $sep10Service->handleRequest($getRequest, new Client());
        self::assertEquals(200, $response->getStatusCode());
        $txB64Xdr = $this->getTransactionFromResponse($response);

        $data = ['transaction' => $txB64Xdr];
        $postRequest = (new ServerRequest())
            ->withMethod('POST')
            ->withUri(new Uri($webAuthEndpoint))
            ->withBody($this->getStreamFromDataArray($data))
            ->withAddedHeader('Content-Type', 'application/json');

        $response = $sep10Service->handleRequest($postRequest, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Invalid number of signatures."}',
            $response->getBody()->__toString(),
        );

        $otherKeyPair = KeyPair::random();
        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($otherKeyPair, Network::testnet()); // invalid signer
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $postRequest = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($postRequest, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Invalid number of valid client account signatures: 0"}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($userKeyPair, Network::testnet()); // valid signer
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $postRequest = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($postRequest, new Client());
        self::assertEquals(200, $response->getStatusCode());
        $this->checkJwt($response, $sep10Config, $userAccountId, $webAuthEndpoint);

        $data = ['account' => $userAccountId, 'memo' => '1234', 'client_domain' => 'client-domain.org'];
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], 'SIGNING_KEY="' . $clientDomainAccountId . '"'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $response = $sep10Service->handleRequest($getRequest->withQueryParams($data), $client);
        self::assertEquals(200, $response->getStatusCode());
        $txB64Xdr = $this->getTransactionFromResponse($response);

        $data = ['transaction' => $txB64Xdr];
        $postRequest = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($postRequest, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Invalid number of signatures."}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $postRequest = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($postRequest, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Invalid number of signatures."}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $tx->sign($otherKeyPair, Network::testnet()); // invalid signer
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $postRequest = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($postRequest, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Invalid number of valid client account signatures: 0"}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($otherKeyPair, Network::testnet());
        $tx->sign($userKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $postRequest = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($postRequest, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Invalid number of valid client domain account signatures: 0"}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $tx->sign($userKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $postRequest = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($postRequest, new Client());
        self::assertEquals(200, $response->getStatusCode());
        $this->checkJwt(
            $response,
            $sep10Config,
            $userAccountId,
            $webAuthEndpoint,
            memo: '1234',
            clientDomain: 'client-domain.org',
        );

        // user account exists
        FriendBot::fundTestAccount($userAccountId);
        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $request = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. No valid client signature found"}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($userKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $request = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Invalid number of client domain account signatures: 0"}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($userKeyPair, Network::testnet());
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $request = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(200, $response->getStatusCode());
        //print $response->getBody()->__toString();

        // update thresholds and signers
        $signer1Kp = KeyPair::random();
        $signer2Kp = KeyPair::random();
        $signer3Kp = KeyPair::random();
        FriendBot::fundTestAccount($signer1Kp->getAccountId());
        FriendBot::fundTestAccount($signer2Kp->getAccountId());
        FriendBot::fundTestAccount($signer3Kp->getAccountId());

        $s1key = new XdrSignerKey();
        $s1key->setType(new XdrSignerKeyType(XdrSignerKeyType::ED25519));
        $s1key->setEd25519($signer1Kp->getPublicKey());

        $s2key = new XdrSignerKey();
        $s2key->setType(new XdrSignerKeyType(XdrSignerKeyType::ED25519));
        $s2key->setEd25519($signer2Kp->getPublicKey());

        $s3key = new XdrSignerKey();
        $s3key->setType(new XdrSignerKeyType(XdrSignerKeyType::ED25519));
        $s3key->setEd25519($signer3Kp->getPublicKey());

        $setOp1 = (new SetOptionsOperationBuilder())
            ->setSigner($s1key, 3)
            ->build();
        $setOp2 = (new SetOptionsOperationBuilder())
            ->setSigner($s2key, 3)
            ->build();
        $setOp3 = (new SetOptionsOperationBuilder())
            ->setSigner($s3key, 4)
            ->build();

        $setOp4 = (new SetOptionsOperationBuilder())
            ->setHighThreshold(20)
            ->setMediumThreshold(10)
            ->setLowThreshold(3)
            ->setMasterKeyWeight(0)
            ->build();
        $sdk = StellarSDK::getTestNetInstance();
        assertNotNull($sdk);
        $accountResponse = $sdk->requestAccount($userAccountId);
        $thresholdsTx = (new TransactionBuilder($accountResponse))
            ->addOperation($setOp1)
            ->addOperation($setOp2)
            ->addOperation($setOp3)
            ->addOperation($setOp4)->build();
        $thresholdsTx->sign($userKeyPair, Network::testnet());
        $txResponse = $sdk->submitTransaction($thresholdsTx);
        assertTrue($txResponse->isSuccessful());
        //print 'ACC: ' . $userAccountId . PHP_EOL;

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($userKeyPair, Network::testnet());
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $request = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Signers with weight 0 do not meet threshold 10"}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($signer1Kp, Network::testnet());
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $request = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Signers with weight 3 do not meet threshold 10"}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($signer1Kp, Network::testnet());
        $tx->sign($signer2Kp, Network::testnet());
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $request = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Signers with weight 6 do not meet threshold 10"}',
            $response->getBody()->__toString(),
        );

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($signer1Kp, Network::testnet());
        $tx->sign($signer2Kp, Network::testnet());
        $tx->sign($signer3Kp, Network::testnet());
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $request = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(200, $response->getStatusCode());

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($signer1Kp, Network::testnet());
        $tx->sign($signer2Kp, Network::testnet());
        $tx->sign($signer3Kp, Network::testnet());
        $tx->sign($userKeyPair, Network::testnet());
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $request = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(200, $response->getStatusCode());

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($signer1Kp, Network::testnet());
        $tx->sign($signer2Kp, Network::testnet());
        $tx->sign($signer3Kp, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $request = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($request, new Client());
        self::assertEquals(400, $response->getStatusCode());
        self::assertEquals(
            '{"error":"Invalid request. Invalid number of client domain account signatures: 0"}',
            $response->getBody()->__toString(),
        );

        $mux = new MuxedAccount($userAccountId, 150);
        $data = ['account' => $mux->getAccountId(), 'client_domain' => 'client-domain.org'];
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], 'SIGNING_KEY="' . $clientDomainAccountId . '"'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $response = $sep10Service->handleRequest($getRequest->withQueryParams($data), $client);
        self::assertEquals(200, $response->getStatusCode());
        $txB64Xdr = $this->getTransactionFromResponse($response);

        $tx = Transaction::fromEnvelopeBase64XdrString($txB64Xdr);
        $tx->sign($signer1Kp, Network::testnet());
        $tx->sign($signer2Kp, Network::testnet());
        $tx->sign($signer3Kp, Network::testnet());
        $tx->sign($clientDomainKeyPair, Network::testnet());
        $transaction = $tx->toEnvelopeXdrBase64();

        $data = ['transaction' => $transaction];
        $postRequest = $postRequest->withBody($this->getStreamFromDataArray($data));

        $response = $sep10Service->handleRequest($postRequest, new Client());
        self::assertEquals(200, $response->getStatusCode());
        $this->checkJwt(
            $response,
            $sep10Config,
            $mux->getAccountId(),
            $webAuthEndpoint,
            clientDomain: 'client-domain.org',
        );
        //print $response->getBody()->__toString();
    }

    public function testInvalidJwt(): void
    {
        $signerKeyPair = KeyPair::random();
        $signerKey = $signerKeyPair->getSecretSeed();
        assertNotNull($signerKey);
        $otherKeyPair = KeyPair::random();
        $otherSignerKey = $otherKeyPair->getSecretSeed();
        assertNotNull($otherSignerKey);

        $iss = 'https://test.com/auth';
        $homeDomain = 'home.com';
        $clientDomain = 'client.com';
        $userKeyPair = KeyPair::random();
        $userId = $userKeyPair->getAccountId();

        $jti = 'test';
        $sub = $userId;
        $currentTime = intval(microtime(true));
        $iat = strval($currentTime);
        $exp = strval(($currentTime + 5 * 60));

        $sep10Jwt = new Sep10Jwt($iss, $sub, $iat, $exp, $jti, homeDomain: $homeDomain, clientDomain: $clientDomain);
        $jwt = $sep10Jwt->sign($signerKey);

        $thrown = false;
        try {
            Sep10Jwt::validateSep10Jwt($jwt, $otherSignerKey);
        } catch (SignatureInvalidException) {
            $thrown = true;
        }
        assertTrue($thrown);

        $thrown = false;
        try {
            Sep10Jwt::validateSep10Jwt($jwt, $signerKey, issuerUrl: 'https://bob.com');
        } catch (UnexpectedValueException) {
            $thrown = true;
        }
        assertTrue($thrown);

        $thrown = false;
        try {
            Sep10Jwt::validateSep10Jwt($jwt, '');
        } catch (InvalidArgumentException) {
            $thrown = true;
        }
        assertTrue($thrown);

        $iat = strval($currentTime + 60);
        $exp = strval(($currentTime + 5 * 60));
        $sep10Jwt = new Sep10Jwt($iss, $sub, $iat, $exp, $jti, homeDomain: $homeDomain, clientDomain: $clientDomain);
        $jwt = $sep10Jwt->sign($signerKey);

        $thrown = false;
        try {
            Sep10Jwt::validateSep10Jwt($jwt, $signerKey);
        } catch (BeforeValidException) {
            $thrown = true;
        }
        assertTrue($thrown);

        $iat = strval($currentTime - 600);
        $exp = strval($currentTime - 60);
        $sep10Jwt = new Sep10Jwt($iss, $sub, $iat, $exp, $jti, homeDomain: $homeDomain, clientDomain: $clientDomain);
        $jwt = $sep10Jwt->sign($signerKey);

        $thrown = false;
        try {
            Sep10Jwt::validateSep10Jwt($jwt, $signerKey);
        } catch (ExpiredException) {
            $thrown = true;
        }
        assertTrue($thrown);
    }

    private function checkJwt(
        ResponseInterface $response,
        Sep10Config $cfg,
        string $clientAccountId,
        string $webAuthEndPoint,
        ?string $memo = null,
        ?string $clientDomain = null,
    ): void {
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));
        $jwt = $decoded['token'];
        assert(is_string($jwt));

        $decodedJwt = JWT::decode($jwt, new Key($cfg->getSep10JWTSigningKey(), 'HS256'));
        //print_r($decodedJwt);
        $decodedJwtArray = (array) $decodedJwt;
        $iss = $decodedJwtArray['iss'];
        assert(is_string($iss));
        assertEquals($webAuthEndPoint, $iss);
        $sub = $decodedJwtArray['sub'];
        assert(is_string($sub));
        if (str_starts_with($clientAccountId, 'M') || $memo === null) {
            assertEquals($clientAccountId, $sub);
        } else {
            assertEquals($clientAccountId . ':' . $memo, $sub);
        }
        $iat = $decodedJwtArray['iat'];
        assert(is_string($iat));
        $exp = $decodedJwtArray['exp'];
        assert(is_string($exp));
        $iatInt = intval($iat);
        $expInt = intval($exp);
        assertTrue($iatInt + $cfg->getJwtTimeout() === $expInt);
        $currentTime = round(microtime(true));
        assertGreaterThanOrEqual($iatInt, $currentTime);
        assertGreaterThanOrEqual($currentTime, $expInt);
        $homeDomain = $decodedJwtArray['home_domain'];
        assert(is_string($homeDomain));
        assertEquals($cfg->getHomeDomains()[0], $homeDomain);
        if ($clientDomain !== null) {
            $jwtClientDomain = $decodedJwtArray['client_domain'];
            assert(is_string($jwtClientDomain));
            assertEquals($clientDomain, $jwtClientDomain);
        }

        $sep10jwt = Sep10Jwt::validateSep10Jwt($jwt, $cfg->getSep10JWTSigningKey(), issuerUrl: $webAuthEndPoint);
        assertEquals($sep10jwt->iss, $iss);
        assertEquals($sep10jwt->iat, $iat);
        assertEquals($sep10jwt->sub, $sub);
        assertEquals($sep10jwt->exp, $exp);
        assertEquals($sep10jwt->homeDomain, $homeDomain);
        if ($clientDomain !== null) {
            assertEquals($clientDomain, $sep10jwt->clientDomain);
        }
        if ($memo !== null) {
            assertEquals($memo, $sep10jwt->accountMemo);
        }
        if (str_starts_with($clientAccountId, 'M')) {
            assertEquals($clientAccountId, $sep10jwt->muxedAccountId);
            $muxedAccount = MuxedAccount::fromAccountId($clientAccountId);
            assertEquals($muxedAccount->getId(), $sep10jwt->muxedId);
        } else {
            assertEquals($clientAccountId, $sep10jwt->accountId);
        }
    }

    private function getTransactionFromResponse(ResponseInterface $response): string
    {
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));
        $txB64Xdr = $decoded['transaction'];
        assert(is_string($txB64Xdr));

        return $txB64Xdr;
    }

    private function validateTransaction(
        string $challengeTransaction,
        string $userAccountId,
        string $serverSigningKey,
        string $serverHomeDomain,
        string $webAuthHost,
        int $timeBoundsGracePeriod,
        Network $network,
        ?string $clientDomainAccountId = null,
        ?int $memo = null,
    ): void {
        $res = base64_decode($challengeTransaction);
        $xdr = new XdrBuffer($res);
        $envelopeXdr = XdrTransactionEnvelope::decode($xdr);

        assertEquals(XdrEnvelopeType::ENVELOPE_TYPE_TX, $envelopeXdr->getType()->getValue());
        $v1 = $envelopeXdr->getV1();
        assertNotNull($v1);
        $transaction = $v1->getTx();
        assertEquals('0', $transaction->getSequenceNumber()->getValue()->toString());

        if ($transaction->getMemo()->getType()->getValue() !== XdrMemoType::MEMO_NONE) {
            assertFalse(str_starts_with($userAccountId, 'M'));
            assertEquals(XdrMemoType::MEMO_ID, $transaction->getMemo()->getType()->getValue());
            if ($memo) {
                assertEquals($transaction->getMemo()->getId(), $memo);
            }
        } else {
            self::assertNull($memo);
        }
        assertNotEquals(0, count($transaction->getOperations()));
        $operations = $transaction->getOperations();
        $count = 0;
        foreach ($operations as $operation) {
            self::assertTrue($operation instanceof XdrOperation);
            $sourceAccount = $operation->getSourceAccount();
            assertNotNull($sourceAccount);

            $opSourceAccountId = MuxedAccount::fromXdr($sourceAccount)->getAccountId();

            if ($count === 0) {
                assertEquals($opSourceAccountId, $userAccountId);
            }
            assertEquals(XdrOperationType::MANAGE_DATA, $operation->getBody()->getType()->getValue());
            $manageDataOperation = $operation->getBody()->getManageDataOperation();
            assertNotNull($manageDataOperation);

            $dataName = $manageDataOperation->getKey();

            if ($count > 0) {
                if ($dataName === 'client_domain') {
                    assertEquals($clientDomainAccountId, $opSourceAccountId);
                } else {
                    assertEquals($serverSigningKey, $opSourceAccountId);
                }
            }

            if ($count === 0) {
                assertEquals($serverHomeDomain . ' auth', $dataName);
            }

            $dataValue = $manageDataOperation->getValue();
            if ($count > 0 && $dataName === 'web_auth_domain') {
                assertEquals($webAuthHost, $dataValue->getValue());
            }
            $count += 1;
        }

        $timeBounds = $transaction->getTimeBounds();
        if ($timeBounds) {
            $grace = 0;
            if ($timeBoundsGracePeriod) {
                $grace = $timeBoundsGracePeriod;
            }
            $currentTime = round(microtime(true));
            self::assertFalse($currentTime < $timeBounds->getMinTimestamp() - $grace);
            self::assertFalse($currentTime > $timeBounds->getMaxTimestamp() + $grace);
        }

        // the envelope must have one signature, and it must be valid: transaction signed by the server
        $signatures = $v1->getSignatures();
        self::assertCount(1, $signatures);
        $firstSignature = $signatures[0];

        assertTrue($firstSignature instanceof XdrDecoratedSignature);

        // validate signature
        $serverKeyPair = KeyPair::fromAccountId($serverSigningKey);
        $transactionHash = AbstractTransaction::fromEnvelopeXdr($envelopeXdr)->hash($network);
        $valid = $serverKeyPair->verifySignature($firstSignature->getSignature(), $transactionHash);
        assertTrue($valid);
    }
}
