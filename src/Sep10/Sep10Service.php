<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep10;

use ArgoNavis\PhpAnchorSdk\Sep01\TomlData;
use ArgoNavis\PhpAnchorSdk\config\IAppConfig;
use ArgoNavis\PhpAnchorSdk\config\ISep10Config;
use ArgoNavis\PhpAnchorSdk\exception\AccountNotLoaded;
use ArgoNavis\PhpAnchorSdk\exception\InvalidConfig;
use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use ArgoNavis\PhpAnchorSdk\exception\TomlDataNotLoaded;
use DateTime;
use Exception;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\ManageDataOperation;
use Soneso\StellarSDK\ManageDataOperationBuilder;
use Soneso\StellarSDK\Memo;
use Soneso\StellarSDK\MuxedAccount;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\Responses\Account\AccountResponse;
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\TimeBounds;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\Xdr\XdrDecoratedSignature;
use Throwable;
use Yosymfony\Toml\Exception\ParseException;
use phpseclib3\Math\BigInteger;

use function assert;
use function base64_decode;
use function base64_encode;
use function bin2hex;
use function count;
use function in_array;
use function intval;
use function is_array;
use function is_int;
use function is_numeric;
use function json_decode;
use function microtime;
use function random_bytes;
use function round;
use function str_starts_with;
use function strlen;
use function strval;

class Sep10Service
{
    public IAppConfig $appConfig;
    public ISep10Config $sep10Config;
    public string $serverAccountId;

    /**
     * @throws InvalidConfig
     */
    public function __construct(
        IAppConfig $appConfig,
        ISep10Config $sep10Config,
    ) {
        $this->appConfig = $appConfig;
        $this->sep10Config = $sep10Config;
        $sep10SigningSeed = $sep10Config->getSep10SigningSeed();
        $homeDomains = $this->sep10Config->getHomeDomains();
        if (count($homeDomains) === 0) {
            throw new InvalidConfig('Invalid sep 10 config: list of home domains is empty');
        }
        try {
            $this->serverAccountId = KeyPair::fromSeed($sep10SigningSeed)->getAccountId();
        } catch (Throwable $e) {
            $message = 'Invalid secret config: SEP-10 signing seed is not a valid secret seed';

            throw new InvalidConfig($message, 0, previous:$e);
        }
    }

    public function handleRequest(ServerRequestInterface $request, ClientInterface $httpClient): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            $content = $request->getBody()->__toString();
            try {
                $queryParams = $request->getQueryParams();
                $challengeRequest = ChallengeRequest::fromQueryParameters($queryParams);

                return $this->createChallenge($challengeRequest, $httpClient);
            } catch (InvalidRequestData $invalid) {
                return new JsonResponse(['error' => 'Invalid request. ' . $invalid->getMessage()], 400);
            }
        } elseif ($request->getMethod() === 'POST') {
            $url = $request->getUri()->__toString();

            try {
                $contentType = $request->getHeaderLine('Content-Type');
                $validationRequest = null;
                if ($contentType === 'application/x-www-form-urlencoded') {
                    $parsedBody = $request->getParsedBody();
                    if (!is_array($parsedBody)) {
                        throw new InvalidRequestData('Invalid body.');
                    }
                    $validationRequest = ValidationRequest::fromDataArray($url, $parsedBody);
                } elseif ($contentType === 'application/json') {
                    $content = $request->getBody()->__toString();
                    $jsonData = @json_decode($content, true);
                    if (!is_array($jsonData)) {
                        throw new InvalidRequestData('Invalid body.');
                    }
                    $validationRequest = ValidationRequest::fromDataArray($url, $jsonData);
                } else {
                    throw new InvalidRequestData('Invalid request type ' . $contentType);
                }

                return $this->handleValidationRequest($validationRequest);
            } catch (InvalidRequestData $invalid) {
                return new JsonResponse(['error' => 'Invalid request. ' . $invalid->getMessage()], 400);
            } catch (Throwable $e) {
                $msg = 'Failed to validate the sep-10 challenge. ' . $e->getMessage();

                return new JsonResponse(['error' => $msg], 500);
            }
        }

        return new TextResponse('Not implemented', status: 501);
    }

    /**
     * @throws AccountNotLoaded
     * @throws InvalidRequestData
     */
    private function handleValidationRequest(ValidationRequest $request): ResponseInterface
    {
        $challenge = $this->parseChallenge($request);
        $homeDomain = $challenge->matchedHomeDomain;
        $clientDomainData = $challenge->clientDomainData;
        $clientAccountId = $challenge->clientAccountId;
        $challengeTx = $challenge->transaction;
        $clientAccount = $this->fetchAccount($clientAccountId, $this->appConfig->getHorizonUrl());

        // if the user account does not exist we need to check if there is only one valid user signature.
        if ($clientAccount === null) {
            $this->validateSignaturesForNonExistentClientAccount(
                $challengeTx,
                $clientAccountId,
                $this->serverAccountId,
                $clientDomainData,
            );
        } else {
            $this->validateSignaturesForExistentClientAccount($challengeTx, $clientAccount, $clientDomainData);
        }

        $jwt = $this->generateSep10Jwt($request->url, $challenge, $homeDomain, $clientDomainData);

        return new JsonResponse(['jwt' => $jwt], 200);
    }

    /**
     * @throws InvalidRequestData
     */
    private function validateSignaturesForExistentClientAccount(
        Transaction $tx,
        AccountResponse $clientAccount,
        ?ClientDomainData $clientDomainData,
    ): void {
        /*
         * If the Client Account exists:
         * The Server gets the signers of the Client Account
         * The Server verifies that one or more signatures are from signers of the Client Account.
         * The Server verifies that there is only one additional signature from the Server Account
         * The Server verifies the weight provided by the signers of the Client Account meets the required threshold(s), if any
         */
        $threshold = $clientAccount->getThresholds()->getMedThreshold();
        $validSigners = [];
        foreach ($clientAccount->getSigners() as $signer) {
            if ($signer->getType() === 'ed25519_public_key') {
                $validSigners[] = $signer;
            }
        }
        if (count($validSigners) === 0) {
            throw new InvalidRequestData('No verifiable signers provided, at least one G... address must be provided.');
        }
        $signatures = $tx->getSignatures();
        $txHash = $tx->hash($this->appConfig->getStellarNetwork());
        $serverAccountKp = KeyPair::fromAccountId($this->serverAccountId);
        $validServerSignatures = 0;
        $validClientDomainSignatures = 0;
        $validClientSignatures = 0;
        $thresholdsSum = 0;
        foreach ($signatures as $signature) {
            assert($signature instanceof XdrDecoratedSignature);
            try {
                $valid = $serverAccountKp->verifySignature($signature->getSignature(), $txHash);
                if ($valid) {
                    $validServerSignatures += 1;

                    continue;
                }
            } catch (Throwable) {
            }
            try {
                if ($clientDomainData !== null) {
                    $clientDomainKeyPair = KeyPair::fromAccountId($clientDomainData->clientDomainAccountId);
                    $valid = $clientDomainKeyPair->verifySignature($signature->getSignature(), $txHash);
                    if ($valid) {
                        $validClientDomainSignatures += 1;

                        continue;
                    }
                }
            } catch (Throwable) {
            }
            foreach ($validSigners as $clientSigner) {
                try {
                    $clientKeyPair = KeyPair::fromAccountId($clientSigner->getKey());
                    $valid = $clientKeyPair->verifySignature($signature->getSignature(), $txHash);
                    if ($valid) {
                        $validClientSignatures += 1;
                        $thresholdsSum += $clientSigner->getWeight();

                        break 1;
                    }
                } catch (Throwable) {
                }
            }
        }
        if ($thresholdsSum < $threshold) {
            $msg = 'Signers with weight ' . strval($thresholdsSum) . ' do not meet threshold ' . strval($threshold);

            throw new InvalidRequestData($msg);
        }
        if ($validClientSignatures === 0) {
            $msg = 'No valid client signature found';

            throw new InvalidRequestData($msg);
        }
        if ($validServerSignatures !== 1) {
            $msg = 'Invalid number of server signatures: ' . strval($validServerSignatures);

            throw new InvalidRequestData($msg);
        }
        if ($clientDomainData !== null && $validClientDomainSignatures !== 1) {
            $msg = 'Invalid number of client domain account signatures: ' . strval($validClientDomainSignatures);

            throw new InvalidRequestData($msg);
        }
    }

    /**
     * @throws InvalidRequestData
     */
    private function validateSignaturesForNonExistentClientAccount(
        Transaction $tx,
        string $clientAccountId,
        string $serverAccountId,
        ?ClientDomainData $clientDomainData = null,
    ): void {
        /*
         * If the Client Account does not exist :
         * The Server verifies the signature count is two (or 3 if client domain is given)
         * The Server verifies that one signature is correct for the master key of the Client Account
         * The Server verified that the other signature is from the Server Account
         * If the transaction has a Manage Data operation with key client_domain,
         * the Server verifies that the source account of the operation signed the
         * transaction and includes an additional client_domain claim in the JWT included in the response
         */

        if (
            ($clientDomainData !== null && count($tx->getSignatures()) !== 3)
            || ($clientDomainData === null && count($tx->getSignatures()) !== 2)
        ) {
            $msg = 'Invalid number of signatures.';

            throw new InvalidRequestData($msg);
        }
        $signatures = $tx->getSignatures();
        $txHash = $tx->hash($this->appConfig->getStellarNetwork());
        $clientAccountKp = KeyPair::fromAccountId($clientAccountId);
        $serverAccountKp = KeyPair::fromAccountId($serverAccountId);
        $validClientAccountSignatures = 0;
        $validClientDomainSignatures = 0;
        $validServerSignatures = 0;
        foreach ($signatures as $signature) {
            assert($signature instanceof XdrDecoratedSignature);
            try {
                $valid = $clientAccountKp->verifySignature($signature->getSignature(), $txHash);
                if ($valid) {
                    $validClientAccountSignatures += 1;

                    continue;
                }
            } catch (Throwable) {
            }
            try {
                $valid = $serverAccountKp->verifySignature($signature->getSignature(), $txHash);
                if ($valid) {
                    $validServerSignatures += 1;

                    continue;
                }
            } catch (Throwable) {
            }
            try {
                if ($clientDomainData !== null) {
                    $clientDomainKeyPair = KeyPair::fromAccountId($clientDomainData->clientDomainAccountId);
                    $valid = $clientDomainKeyPair->verifySignature($signature->getSignature(), $txHash);
                    if ($valid) {
                        $validClientDomainSignatures += 1;

                        continue;
                    }
                }
            } catch (Throwable) {
            }
        }
        if ($validClientAccountSignatures !== 1) {
            $msg = 'Invalid number of valid client account signatures: ' . strval($validClientAccountSignatures);

            throw new InvalidRequestData($msg);
        }
        if ($validServerSignatures !== 1) {
            $msg = 'Invalid number of valid server signatures: ' . strval($validServerSignatures);

            throw new InvalidRequestData($msg);
        }
        if ($clientDomainData !== null && $validClientDomainSignatures !== 1) {
            $msg = 'Invalid number of valid client domain account signatures: ' . strval($validClientDomainSignatures);

            throw new InvalidRequestData($msg);
        }
        $nrOfValidSignatures = $validClientAccountSignatures + $validServerSignatures + $validClientDomainSignatures;
        if ($nrOfValidSignatures !== count($signatures)) {
            $msg = 'Invalid number of signatures: ' . strval(count($signatures));

            throw new InvalidRequestData($msg);
        }
    }

    /**
     * @throws AccountNotLoaded
     */
    private function fetchAccount(string $accountId, string $horizonUrl): ?AccountResponse
    {
        $sdk = new StellarSDK($horizonUrl);
        $accId = $accountId;
        if (str_starts_with($accountId, 'M')) {
            $mux = MuxedAccount::fromAccountId($accountId);
            $accId = $mux->getEd25519AccountId();
        }
        $account = null;
        try {
            if ($sdk->accountExists($accId)) {
                $account = $sdk->accounts()->account($accId);
            }
        } catch (Throwable $e) {
           // could not fetch client account.
            $msg = 'Could not fetch account from horizon ' . $horizonUrl;
            $msg .= ' Error: ' . $e->getMessage();

            throw new AccountNotLoaded($msg, 400, $e);
        }

        return $account;
    }

    /**
     * @throws InvalidRequestData
     */
    private function parseChallenge(ValidationRequest $request): ChallengeTransaction
    {
        $serverAccountId = $this->serverAccountId;
        $network = $this->appConfig->getStellarNetwork();
        $domainNames = $this->sep10Config->getHomeDomains();
        $webAuthDomain = $this->sep10Config->getWebAuthDomain();
        if ($webAuthDomain === null) {
            $webAuthDomain = $this->sep10Config->getHomeDomains()[0];
        }
        $tx = null;
        try {
            $tx = Transaction::fromEnvelopeBase64XdrString($request->transaction);
        } catch (Throwable $e) {
            throw new InvalidRequestData('Transaction could not be parsed', 0, $e);
        }

        if (!($tx instanceof Transaction)) {
            throw new InvalidRequestData('Transaction cannot be a fee bump transaction');
        }

        // verify that transaction source account is equal to the server's signing key
        if ($serverAccountId !== $tx->getSourceAccount()->getAccountId()) {
            throw new InvalidRequestData('Transaction source account is not equal to server account.');
        }

        // verify that transaction sequenceNumber is equal to zero
        if (!$tx->getSequenceNumber()->equals(new BigInteger(0))) {
            throw new InvalidRequestData('The transaction sequence number should be zero.');
        }

        $memo = $tx->getMemo();
        if ($memo->getType() !== Memo::MEMO_TYPE_NONE && $memo->getType() !== Memo::MEMO_TYPE_ID) {
            throw new InvalidRequestData('Only memo type `id` is supported');
        }

        $maxTime = $tx->getTimeBounds()?->getMaxTime();
        $minTime = $tx->getTimeBounds()?->getMinTime();
        if ($maxTime === null || $minTime === null) {
            throw new InvalidRequestData('Transaction requires timebounds');
        }
        if ($maxTime->getTimestamp() === 0) {
            throw new InvalidRequestData('Transaction requires non-infinite timebounds.');
        }
        $grace = 60 * 5;
        $currentTime = round(microtime(true));
        if (
            $currentTime < $minTime->getTimestamp() - $grace ||
            $currentTime > $maxTime->getTimestamp() + $grace
        ) {
            throw new InvalidRequestData('Transaction is not within range of the specified timebounds.');
        }

        if (count($tx->getOperations()) < 1) {
            throw new InvalidRequestData('Transaction requires at least one ManageData operation.');
        }

        // verify that the first operation in the transaction is a Manage Data operation
        // and its source account is not null
        $op = $tx->getOperations()[0];
        if (!($op instanceof ManageDataOperation)) {
            throw new InvalidRequestData('Operation type should be ManageData.');
        }
        $clientAccountId = $op->getSourceAccount();
        if ($clientAccountId === null) {
            throw new InvalidRequestData('Operation must have a source account.');
        }

        $matchedDomainName = null;
        foreach ($domainNames as $homeDomain) {
            if ($homeDomain . ' auth' === $op->getKey()) {
                $matchedDomainName = $homeDomain;
            }
        }

        if ($matchedDomainName === null) {
            $msg = 'The transaction operation key name does not include one of the expected home domains.';

            throw new InvalidRequestData($msg);
        }

        // verify manage data value
        $dataValue = $op->getValue();
        if ($dataValue === null) {
            throw new InvalidRequestData('The transaction operation value should not be null.');
        }
        if (strlen($dataValue) !== 64) {
            throw new InvalidRequestData('Random nonce encoded as base64 should be 64 bytes long.');
        }
        $nonce = base64_decode($dataValue);
        if (strlen($nonce) !== 48) {
            throw new InvalidRequestData('Random nonce before encoding as base64 should be 48 bytes long.');
        }

        $clientDomainData = null;
        // verify subsequent operations are manage data ops with source account set to server account
        $operations = $tx->getOperations();
        for ($i = 1; $i < count($operations); $i++) {
            $operation = $operations[$i];
            if (!($operation instanceof ManageDataOperation)) {
                throw new InvalidRequestData('Operation type should be ManageData.');
            }
            $sourceAccount = $operation->getSourceAccount();
            if ($sourceAccount === null) {
                throw new InvalidRequestData('Operation should have a source account.');
            }
            if (
                $operation->getKey() !== 'client_domain'
                && $sourceAccount->getAccountId() !== $serverAccountId
            ) {
                throw new InvalidRequestData('Subsequent operations are unrecognized.');
            }
            if ($operation->getKey() === 'web_auth_domain') {
                if ($operation->getValue() === null) {
                    throw new InvalidRequestData('web_auth_domain operation value should not be null.');
                }
                if ($webAuthDomain !== $operation->getValue()) {
                    throw new InvalidRequestData('web_auth_domain operation value does not match ' . $webAuthDomain);
                }
            }
            if ($operation->getKey() === 'client_domain') {
                $clientDomain = $operation->getValue();
                $clientDomainAccountId = $operation->getSourceAccount()?->getEd25519AccountId();
                if ($clientDomain === null) {
                    throw new InvalidRequestData('client_domain operation value should not be null.');
                }
                if ($clientDomainAccountId === null) {
                    throw new InvalidRequestData('client_domain operation should have a source account.');
                }
                $clientDomainData = new ClientDomainData(
                    $clientDomain,
                    $clientDomainAccountId,
                );
            }
        }

        $this->verifyServerSignature($tx, $serverAccountId, $network);

        return new ChallengeTransaction($tx, $clientAccountId->getAccountId(), $matchedDomainName, $clientDomainData);
    }

    /**
     * @throws InvalidRequestData
     */
    private function verifyServerSignature(Transaction $tx, string $serverAccountId, Network $network): void
    {
        $signatures = $tx->getSignatures();
        if (count($signatures) === 0) {
            throw new InvalidRequestData('Transaction has no signatures.');
        }
        $firstSignature = $signatures[0];
        assert($firstSignature instanceof XdrDecoratedSignature);

        // validate signature
        $serverKeyPair = KeyPair::fromAccountId($serverAccountId);
        $transactionHash = $tx->hash($network);
        try {
            $valid = $serverKeyPair->verifySignature($firstSignature->getSignature(), $transactionHash);
            if (!$valid) {
                throw new InvalidRequestData('Transaction not signed by server: ' . $serverAccountId);
            }
        } catch (Throwable) {
            throw new InvalidRequestData('Transaction not signed by server: ' . $serverAccountId);
        }
    }

    private function createChallenge(ChallengeRequest $request, ClientInterface $httpClient): ResponseInterface
    {
        $memo = Memo::none();
        try {
            $this->validateChallengeRequestFormat($request);
            $memo = $this->validateChallengeRequestMemo($request);
        } catch (InvalidRequestData $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        }

        return $this->createChallengeResponse($request, $memo, $httpClient);
    }

    /**
     * @throws InvalidRequestData
     */
    private function validateChallengeRequestFormat(ChallengeRequest $request): void
    {
        // validate home domain
        $homeDomain = $request->homeDomain;
        if ($homeDomain === null) {
            $request->homeDomain = $this->sep10Config->getHomeDomains()[0];
        } elseif (!in_array($homeDomain, $this->sep10Config->getHomeDomains())) {
            throw new InvalidRequestData('home_domain ' . $homeDomain . ' not supported', 400);
        }

        // validate account
        try {
            KeyPair::fromAccountId($request->account);
        } catch (Throwable $e) {
            throw new InvalidRequestData('client wallet account ' . $request->account . ' is invalid', 400);
        }

        // validate client
        $custodialAccountList = $this->sep10Config->getKnownCustodialAccountList() ?? [];
        $custodialWallet = in_array($request->account, $custodialAccountList);

        if ($custodialWallet && $request->clientDomain !== null) {
            $errorMsg = 'client_domain must not be specified if the account is an custodial-wallet account';

            throw new InvalidRequestData($errorMsg, 400);
        }

        if (!$custodialWallet && $this->sep10Config->isClientAttributionRequired()) {
            if ($request->clientDomain === null) {
                throw new InvalidRequestData('client_domain is required', 400);
            }
            $allowList = $this->sep10Config->getAllowedClientDomains() ?? [];
            if (!in_array($request->clientDomain, $allowList)) {
                // client_domain provided is not in the configured allow list
                throw new InvalidRequestData('unable to process', 403);
            }
        }
    }

    /**
     * @throws InvalidRequestData
     */
    public function validateChallengeRequestMemo(ChallengeRequest $request): Memo
    {
        // validate memo
        $memo = Memo::none();
        if ($request->memo !== null) {
            if (str_starts_with($request->account, 'M')) {
                throw new InvalidRequestData('memo not allowed for muxed accounts', 400);
            }
            try {
                if (is_numeric($request->memo) && is_int($request->memo + 0)) {
                    $value = intval($request->memo);
                    $memo = Memo::id($value);
                } else {
                    throw new InvalidRequestData('invalid memo value: ' . $request->memo, 400);
                }
            } catch (Throwable $e) {
                throw new InvalidRequestData('invalid memo value: ' . $request->memo, 400, $e);
            }
        }

        return $memo;
    }

    private function createChallengeResponse(
        ChallengeRequest $request,
        Memo $memo,
        ClientInterface $httpClient,
    ): ResponseInterface {
        $clientSigningKey = null;
        try {
            if ($request->clientDomain !== null) {
                $msg = 'client signing key not found for domain ' . $request->clientDomain;
                try {
                    $tomlData = TomlData::fromDomain($request->clientDomain, $httpClient);
                    $clientSigningKey = $tomlData->generalInformation?->signingKey;
                    if ($clientSigningKey === null) {
                        return new JsonResponse(['error' => $msg], 400);
                    }
                } catch (TomlDataNotLoaded $tnl) {
                    return new JsonResponse(['error' => $msg . ' : ' . $tnl->getMessage()], 400);
                } catch (ParseException $pse) {
                    return new JsonResponse(['error' => $msg . ' : ' . $pse->getMessage()], 400);
                }
            }

            $sep10SigningSeed = $this->sep10Config->getSep10SigningSeed();
            $signer = KeyPair::fromSeed($sep10SigningSeed);
            $start = new DateTime('now');
            $end = new DateTime('now');
            $secondsToAdd = $this->sep10Config->getAuthTimeout();
            $end->modify("+$secondsToAdd seconds");
            $homeDomain = $request->homeDomain;
            if ($homeDomain === null) {
                $homeDomain = $this->sep10Config->getHomeDomains()[0];
            }
            $webAuthDomain = $this->sep10Config->getWebAuthDomain();
            if ($webAuthDomain === null) {
                $webAuthDomain = $this->sep10Config->getHomeDomains()[0];
            }

            return $this->newChallenge(
                signer: $signer,
                network: $this->appConfig->getStellarNetwork(),
                clientAccountId: $request->account,
                domainName: $homeDomain,
                webAuthDomain: $webAuthDomain,
                timeBounds: new TimeBounds($start, $end),
                memo: $memo,
                clientDomain: $request->clientDomain,
                clientSigningKey: $clientSigningKey,
            );
        } catch (Throwable $e) {
            return new JsonResponse(['error' => 'Failed to create the sep-10 challenge. ' . $e->getMessage()], 500);
        }
    }

    /**
     * @throws Exception
     */
    private function newChallenge(
        KeyPair $signer,
        Network $network,
        string $clientAccountId,
        string $domainName,
        string $webAuthDomain,
        TimeBounds $timeBounds,
        Memo $memo,
        ?string $clientDomain = null,
        ?string $clientSigningKey = null,
    ): ResponseInterface {
        $sourceAccount = new Account($signer->getAccountId(), new BigInteger(-1));
        $clientAccount = MuxedAccount::fromAccountId($clientAccountId);
        $nonce = random_bytes(48);
        $builder = new ManageDataOperationBuilder($domainName . ' auth', base64_encode($nonce));
        $builder->setMuxedSourceAccount($clientAccount);
        $domainNameOperation = $builder->build();

        $builder = new ManageDataOperationBuilder('web_auth_domain', $webAuthDomain);
        $builder->setSourceAccount($signer->getAccountId());
        $webAuthDomainOperation = $builder->build();

        $txBuilder = (new TransactionBuilder($sourceAccount))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)
            ->addMemo($memo)
            ->setTimeBounds($timeBounds);

        if ($clientDomain !== null && $clientSigningKey !== null) {
            $builder = new ManageDataOperationBuilder('client_domain', $clientDomain);
            $builder->setSourceAccount($clientSigningKey);
            $txBuilder->addOperation($builder->build());
        }

        $tx = $txBuilder->build();
        $tx->sign($signer, $network);
        $response = ['transaction' => $tx->toEnvelopeXdrBase64(),
            'network_passphrase' => $network->getNetworkPassphrase(),
        ];

        return new JsonResponse($response, 200);
    }

    private function generateSep10Jwt(
        string $url,
        ChallengeTransaction $challenge,
        ?string $homeDomain = null,
        ?ClientDomainData $clientDomainData = null,
    ): string {
        $issuedAt = $challenge->transaction->getTimeBounds()?->getMinTime()->getTimestamp();
        $memo = $challenge->transaction->getMemo();
        $sub = $challenge->clientAccountId;
        if ($memo->getType() === Memo::MEMO_TYPE_ID) {
            if (is_int($memo->getValue())) {
                $value = intval($memo->getValue());
                $sub .= ':' . strval($value);
            }
        }
        $jwtTimeOut = $this->sep10Config->getJwtTimeout();
        $exp = $issuedAt + $jwtTimeOut;
        $txHash = $challenge->transaction->hash($this->appConfig->getStellarNetwork());

        $sep10Jwt = new Sep10Jwt(
            $url,
            $sub,
            strval($issuedAt),
            strval($exp),
            bin2hex($txHash),
            $homeDomain,
            $clientDomainData?->clientDomain,
        );

        return $sep10Jwt->sign($this->sep10Config->getSep10JWTSigningKey());
    }
}
