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
use Throwable;
use Yosymfony\Toml\Exception\ParseException;
use phpseclib3\Math\BigInteger;

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

/**
 * The Sep10Service handles Stellar Web Authentication requests as defined by
 * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0010.md">SEP-10</a>
 *
 * To create an instance of the service you have to pass an app config (IAppConfig) and a sep 10 config
 * (ISep10Config) to its constructor. The app config defines the network to be used (such as mainnet, testnet) for
 * signing the SEP-10 challenge transaction and the sep 10 config contains config values specific for
 * the SEP-10 implementation, such as the signing keys for the challenge and the jwt token,
 * timeouts for the jwt token and challenge, etc.
 *
 * After initializing the service it can be used within the server implementation by passing all
 * SEP-10 auth requests to its method handleRequest. It will handle them and return the corresponding response
 * that can be sent back to the client. No further interaction or callback is needed.
 *
 * See also: <a href="https://github.com/Argo-Navis-Dev/php-anchor-sdk/blob/main/docs/sep-10.md">SDK SEP-10 docs</a>
 */
class Sep10Service
{
    public IAppConfig $appConfig;
    public ISep10Config $sep10Config;
    public string $serverAccountId;

    /**
     * Constructor.
     *
     * @param IAppConfig $appConfig app config containing the information about the network (mainnet, testnet, etc.)
     * to be used for signing the SEP-10 transaction challenge.
     * @param ISep10Config $sep10Config sep 10 config containing the config values specific for
     *  the SEP-10 implementation, such as the signing keys for the challenge and the jwt token,
     *  timeouts for the jwt token and challenge, etc.
     *
     * @throws InvalidConfig if the sep 10 config contains invalid values.
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

    /**
     * Handles a forwarded client request specified by SEP-10. Builds and returns the corresponding response,
     * that can be sent back to the client.
     *
     * @param ServerRequestInterface $request the request from the client as defined in
     * <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>.
     * @param ClientInterface $httpClient the http client that will be used to make network requests if needed.
     * For example if it needs to load the client signing key for a given client domain in the request.
     * As defined in <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>. For example Guzzle HTTP Client.
     *
     * @return ResponseInterface the response that should be sent back to the client.
     * As defined in <a href="https://www.php-fig.org/psr/psr-7/">PSR 7</a>
     */
    public function handleRequest(ServerRequestInterface $request, ClientInterface $httpClient): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            // Challenge
            try {
                $queryParams = $request->getQueryParams();
                $challengeRequest = ChallengeRequest::fromQueryParameters($queryParams);

                return $this->createChallenge($challengeRequest, $httpClient);
            } catch (InvalidRequestData $invalid) {
                return new JsonResponse(['error' => 'Invalid request. ' . $invalid->getMessage()], 400);
            }
        } elseif ($request->getMethod() === 'POST') {
            // Token
            try {
                $url = $request->getUri()->__toString();
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
     * Handles the validation request containing the transaction signed by the client/user.
     *
     * @param ValidationRequest $request request data containing the signed transaction.
     *
     * @return ResponseInterface the response to be sent back to the client. It contains the generated jwt token.
     *
     * @throws AccountNotLoaded
     * @throws InvalidRequestData if the signed transaction is invalid.
     */
    private function handleValidationRequest(ValidationRequest $request): ResponseInterface
    {
        $challenge = $this->parseChallenge($request);
        $homeDomain = $challenge->matchedHomeDomain;
        $clientDomainData = $challenge->clientDomainData;
        $clientAccountId = $challenge->clientAccountId;
        $challengeTx = $challenge->transaction;

        // load the client account if exists. we need it later to be able to check the signatures.
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

        return new JsonResponse(['token' => $jwt], 200);
    }

    /**
     * Verifies that the transaction is correctly signed by the client (user) account id and server account id.
     * If the client domain is set it also verifies that the transaction has been signed by the client domain id.
     *
     * This is for the case that the client account exist on the stellar network.
     *
     * @throws InvalidRequestData if the transaction was not signed correctly.
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
            //assert($signature instanceof XdrDecoratedSignature);
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
     * Verifies that the transaction is correctly signed by the client (user) account id and server account id.
     * If the client domain is set it also verifies that the transaction has been signed by the client domain id.
     *
     * This is for the case that the client account does not exist on the stellar network.
     *
     * @param Transaction $tx the transaction to verify if correctly signed by the client
     * @param string $clientAccountId the account id of the client that should have signed the transaction
     * @param string $serverAccountId the server account id
     *
     * @throws InvalidRequestData if not correctly signed.
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
            //assert($signature instanceof XdrDecoratedSignature);
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
     * Parses the data from the signed transaction included in the request.
     * Also validates the transaction data.
     *
     * @param ValidationRequest $request the request data containing the signed transaction.
     *
     * @return ChallengeTransaction containing the parsed data
     *
     * @throws InvalidRequestData if the transaction data is invalid.
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
            // decode the received input as a base64-urlencoded XDR representation of Stellar transaction envelope
            $tx = Transaction::fromEnvelopeBase64XdrString($request->transaction);
        } catch (Throwable $e) {
            throw new InvalidRequestData('Transaction could not be parsed', 0, $e);
        }

        // verify that transaction is not a fee bump transaction
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

        // if the transaction contains a memo, then verify that the memo is of type id.
        $memo = $tx->getMemo();
        if ($memo->getType() !== Memo::MEMO_TYPE_NONE && $memo->getType() !== Memo::MEMO_TYPE_ID) {
            throw new InvalidRequestData('Only memo type `id` is supported');
        }

        // verify that transaction has time bounds set, and that current time is between the minimum and maximum bounds
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

        // verify that transaction contains at least one operation
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

        // verify that the first operations key value matches to one of our home domains.
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

        // verify first operations data value
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
            // verify that the operation has a source account
            $sourceAccount = $operation->getSourceAccount();
            if ($sourceAccount === null) {
                throw new InvalidRequestData('Operation should have a source account.');
            }
            // if the operations key is client_domain then the source account should not be the server account id.
            if (
                $operation->getKey() !== 'client_domain'
                && $sourceAccount->getAccountId() !== $serverAccountId
            ) {
                throw new InvalidRequestData('Subsequent operations are unrecognized.');
            }

            // if the operation key is web_auth_domain then verify that the value contains our web auth domain.
            if ($operation->getKey() === 'web_auth_domain') {
                if ($operation->getValue() === null) {
                    throw new InvalidRequestData('web_auth_domain operation value should not be null.');
                }
                if ($webAuthDomain !== $operation->getValue()) {
                    throw new InvalidRequestData('web_auth_domain operation value does not match ' . $webAuthDomain);
                }
            }

            // collect client domain data needed later for signature verification.
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

        // verify that transaction envelope has a correct signature by the Server Account
        $this->verifyServerSignature($tx, $serverAccountId, $network);

        return new ChallengeTransaction($tx, $clientAccountId->getAccountId(), $matchedDomainName, $clientDomainData);
    }

    /**
     * Verifies that the given transaction has been signed by the given server account id.
     *
     * @param Transaction $tx the transaction to verify if it was signed by the server account id.
     * @param string $serverAccountId the server account id that must match the signature.
     * @param Network $network the network, that the transaction was signed for.
     *
     * @throws InvalidRequestData if the transaction was not signed by the given server account id.
     */
    private function verifyServerSignature(Transaction $tx, string $serverAccountId, Network $network): void
    {
        $signatures = $tx->getSignatures();
        if (count($signatures) === 0) {
            throw new InvalidRequestData('Transaction has no signatures.');
        }
        $firstSignature = $signatures[0];
        //assert($firstSignature instanceof XdrDecoratedSignature);

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

    /**
     * Creates the SEP-10 auth challenge transaction that needs to be signed by the client.
     * Before creating the challenge transaction it validates the given request data.
     *
     * @param ChallengeRequest $request containing the data from the client request.
     * @param ClientInterface $httpClient http client to be used for network requests if needed.
     *
     * @return ResponseInterface the response containing the SEP-10 auth challenge transaction.
     * The response can be sent back to the client.
     */
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
     * Checks if the data from the request is valid and supported, and we can process it.
     * - if a home domain was specified in the request, we check if we support it
     * - we check the given account id to see if it is a valid stellar account id
     * - we check if the given account belongs to our known custodial accounts list. If yes, the request comes
     * from a known custodial wallet.
     * - if the request comes from a known custodial wallet, the client domain should not be set in the request
     * - if the request is not from a known custodial wallet, we must check if the anchor requires client attribution.
     * If yes, the client domain must be available in the request data. If available, we must also check if the given
     * client domain is blacklisted by the anchor.
     *
     * @throws InvalidRequestData if data is not valid or supported. Such as invalid account id or blacklisted
     * client domain.
     */
    private function validateChallengeRequestFormat(ChallengeRequest $request): void
    {
        // Validate home domain
        // If a home domain was specified in the request, we check if we support it
        $homeDomain = $request->homeDomain;
        if ($homeDomain === null) {
            $request->homeDomain = $this->sep10Config->getHomeDomains()[0];
        } elseif (!in_array($homeDomain, $this->sep10Config->getHomeDomains())) {
            throw new InvalidRequestData('home_domain ' . $homeDomain . ' not supported', 400);
        }

        // Validate account to see if it is a valid stellar account id
        try {
            KeyPair::fromAccountId($request->account);
        } catch (Throwable $e) {
            throw new InvalidRequestData('client wallet account ' . $request->account . ' is invalid', 400);
        }

        // Validate client. Is the request coming from a known custodial wallet?
        $custodialAccountList = $this->sep10Config->getKnownCustodialAccountList() ?? [];
        $custodialWallet = in_array($request->account, $custodialAccountList);

        // If the request comes from a known custodial wallet, the client domain should not be set in the request.
        if ($custodialWallet && $request->clientDomain !== null) {
            $errorMsg = 'client_domain must not be specified if the account is a custodial-wallet account';

            throw new InvalidRequestData($errorMsg, 400);
        }

        // If not a known custodial wallet, and client attribution is required by the anchor
        // then we must check if the request contains a client domain and if the contained client domain
        // is not blacklisted by the anchor.
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
     * If the client challenge request contains a memo, then checks if the given memo is valid and supported:
     * - if the account from the client request is a muxed account, then no memo is allowed in the request because the
     * account already contains the memo.
     * - the given memo must be numeric so that we can create a Memo object from it of type id.
     * Otherwise, the memo is not supported.
     *
     * @throws InvalidRequestData if the request data contains a memo which is invalid or not supported.
     */
    public function validateChallengeRequestMemo(ChallengeRequest $request): Memo
    {
        // validate memo
        $memo = Memo::none();
        if ($request->memo !== null) {
            // If the given account id from the request data is a muxed account id, then no memo is accepted
            // in the request because the account id already contains the memo.
            if (str_starts_with($request->account, 'M')) {
                throw new InvalidRequestData('memo not allowed for muxed accounts', 400);
            }
            try {
                // check if the memo is an integer. We only support memos of type id.
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

    /**
     * Composes the SEP-10 auth challenge transaction from the given client request data.
     * Requires already validated request data. Loads the client signing key by http request if needed.
     *
     * @param ChallengeRequest $request the request data
     * @param Memo $memo the memo from the request data as Memo object.
     * @param ClientInterface $httpClient the http client to make network requests if needed.
     *
     * @return ResponseInterface the response for the client containing the challenge transaction.
     */
    private function createChallengeResponse(
        ChallengeRequest $request,
        Memo $memo,
        ClientInterface $httpClient,
    ): ResponseInterface {
        $clientSigningKey = null;
        try {
            // load the client signing key by http request if needed.
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
     * Composes the SEP auth challenge transaction by using the given data that was extracted from
     * the client request, the config data and loaded by http request if needed (client signing key).
     * The given data must be completely validated and supported.
     *
     * @throws Exception if an error occurs while creating and signing the transaction.
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
        // set tx source account to the Server Account
        // set invalid sequence number (set to 0) so the transaction cannot be run on the Stellar network
        // here we set it to -1 because the classic php sdk will automatically increment it when preparing the transaction.
        $sourceAccount = new Account($signer->getAccountId(), new BigInteger(-1));

        // client account (account of the user).
        $clientAccount = MuxedAccount::fromAccountId($clientAccountId);

        // Operations:

        // 1. manage_data(source: client account, key: '<home domain> auth', value: random_nonce())
        // The value of key is the Home Domain, followed by auth.
        // The value must be 64 bytes long. It contains a 48 byte cryptographic-quality random string
        // encoded using base64 (for a total of 64 bytes after encoding).
        $nonce = random_bytes(48);
        $builder = new ManageDataOperationBuilder($domainName . ' auth', base64_encode($nonce));
        $builder->setMuxedSourceAccount($clientAccount);
        $domainNameOperation = $builder->build();

        // 2. manage_data(source: server account, key: 'web_auth_domain', value: web_auth_domain)
        // The source account is the Server Account
        // The value is the Server's domain.
        $builder = new ManageDataOperationBuilder('web_auth_domain', $webAuthDomain);
        $builder->setSourceAccount($signer->getAccountId());
        $webAuthDomainOperation = $builder->build();

        // prepare the transaction
        $txBuilder = (new TransactionBuilder($sourceAccount))
            ->addOperation($domainNameOperation)
            ->addOperation($webAuthDomainOperation)
            ->addMemo($memo)
            ->setTimeBounds($timeBounds);

        // Operation 3. (optional) manage_data(source: client domain account, key: 'client_domain', value: client_domain)
        // SEP-10 says : Add this operation if the server supports Verifying the Client Domain and the client provided
        // a client_domain parameter in the request.
        // => If we have the needed data, we also add this operation
        if ($clientDomain !== null && $clientSigningKey !== null) {
            $builder = new ManageDataOperationBuilder('client_domain', $clientDomain);
            // The source account is the Client Domain Account
            $builder->setSourceAccount($clientSigningKey);
            $txBuilder->addOperation($builder->build());
        }

        // build the transaction
        $tx = $txBuilder->build();

        // sign the transaction. SEP-10: signature by the Server Account
        // we use the network given by the app config here.
        $tx->sign($signer, $network);

        // build the response for the client containing the
        $response = ['transaction' => $tx->toEnvelopeXdrBase64(),
            'network_passphrase' => $network->getNetworkPassphrase(),
        ];

        return new JsonResponse($response, 200);
    }

    /**
     * Generates the jwt token by using the given (verified) request and config data.
     *
     * @param string $url the request url (the principal that issued a token)
     * @param ChallengeTransaction $challenge the challenge transaction data.
     * @param string|null $homeDomain the home domain to be added to the jwt token if any
     * @param ClientDomainData|null $clientDomainData the client domain data to be added to the jwt token if any.
     *
     * @return string the jwt token
     */
    private function generateSep10Jwt(
        string $url,
        ChallengeTransaction $challenge,
        ?string $homeDomain = null,
        ?ClientDomainData $clientDomainData = null,
    ): string {
        // iat (the time at which the JWT was issued RFC7519, Section 4.1.6)
        // SEP-10 says: The Server should not provide more than one JWT for a specific challenge transaction.
        // therefore we take the issued time from the transaction.
        $issuedAt = $challenge->transaction->getTimeBounds()?->getMinTime()->getTimestamp();
        $memo = $challenge->transaction->getMemo();

        // (sub: the principal that is the subject of the JWT, RFC7519, Section 4.1.2) —
        // there are several possible formats:
        //
        // If the Client Account is a muxed account (M...), the sub value should be the muxed account (M...).
        // If the Client Account is a stellar account (G...):
        //  - And, a memo was attached to the challenge transaction, the sub should be the stellar account appended
        //    with the memo, separated by a colon (G...:17509749319012223907).
        //  - Otherwise, the sub value should be Stellar account (G...).
        $sub = $challenge->clientAccountId;
        if ($memo->getType() === Memo::MEMO_TYPE_ID) {
            if (is_int($memo->getValue())) {
                $value = intval($memo->getValue());
                $sub .= ':' . strval($value);
            }
        }
        $jwtTimeOut = $this->sep10Config->getJwtTimeout();

        // the expiration time
        $exp = $issuedAt + $jwtTimeOut;

        // transaction hash
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

        // sign the jwt token with the signing key from config
        return $sep10Jwt->sign($this->sep10Config->getSep10JWTSigningKey());
    }
}
