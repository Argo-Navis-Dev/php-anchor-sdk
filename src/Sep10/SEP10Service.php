<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep10;

use ArgoNavis\PhpAnchorSdk\Sep01\TomlData;
use ArgoNavis\PhpAnchorSdk\config\IAppConfig;
use ArgoNavis\PhpAnchorSdk\config\ISecretConfig;
use ArgoNavis\PhpAnchorSdk\config\ISep10Config;
use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSigningSeed;
use DateTime;
use Exception;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\ManageDataOperationBuilder;
use Soneso\StellarSDK\Memo;
use Soneso\StellarSDK\MuxedAccount;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\TimeBounds;
use Soneso\StellarSDK\TransactionBuilder;
use Throwable;
use phpseclib3\Math\BigInteger;

use function in_array;
use function intval;
use function is_array;
use function json_decode;
use function random_bytes;

class SEP10Service
{
    public IAppConfig $appConfig;
    public ISecretConfig $secretConfig;
    public ISep10Config $sep10Config;
    public string $serverAccountId;

    /**
     * @throws InvalidSigningSeed
     */
    public function __construct(
        IAppConfig $appConfig,
        ISecretConfig $secretConfig,
        ISep10Config $sep10Config,
    ) {
        $this->appConfig = $appConfig;
        $this->secretConfig = $secretConfig;
        $this->sep10Config = $sep10Config;
        $sep10SigningSeed = $secretConfig->getSep10SigningSeed();
        if (!isset($sep10SigningSeed)) {
            throw new InvalidSigningSeed('Invalid secret config: SEP-10 signing seed is not set');
        }

        try {
            $this->serverAccountId = KeyPair::fromSeed($sep10SigningSeed)->getAccountId();
        } catch (Throwable $e) {
            $message = 'Invalid secret config: SEP-10 signing seed is not a valid secret seed';

            throw new InvalidSigningSeed($message, 0, previous:$e);
        }
    }

    public function handleRequest(ServerRequestInterface $request, ClientInterface $client): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            $content = $request->getBody()->__toString();

            try {
                $jsonData = @json_decode($content, true);
                if (!is_array($jsonData)) {
                    throw new InvalidRequestData('Invalid json body.');
                }
                $challengeRequest = ChallengeRequest::fromJson($jsonData);

                return $this->createChallenge($challengeRequest, $client);
            } catch (InvalidRequestData $invalid) {
                return new JsonResponse(['error' => 'Invalid request. ' . $invalid->getMessage()], 400);
            }
        }

        return new TextResponse('Not implemented', status: 501);
    }

    private function createChallenge(ChallengeRequest $request, ClientInterface $httpClient): ResponseInterface
    {
        $response = $this->validateChallengeRequestFormat($request);
        if ($response !== null) {
            return $response;
        }

        $result = $this->validateChallengeRequestMemo($request);
        if ($result instanceof ResponseInterface) {
            return $result;
        }
        $memo = $result;

        return $this->createChallengeResponse($request, $memo, $httpClient);
    }

    private function validateChallengeRequestFormat(ChallengeRequest $request): ?ResponseInterface
    {
        // validate home domain
        $homeDomain = $request->homeDomain;
        if ($homeDomain === null) {
            $request->homeDomain = $this->sep10Config->getHomeDomains()[0];
        } elseif (!in_array($homeDomain, $this->sep10Config->getHomeDomains())) {
            return new JsonResponse(['error' => 'home_domain ' . $homeDomain . ' not supported'], 400);
        }

        // validate account
        try {
            KeyPair::fromAccountId($request->account);
        } catch (Throwable $e) {
            return new JsonResponse(['error' => 'client wallet account ' . $request->account . ' is invalid'], 400);
        }

        // validate client
        $custodialAccountList = $this->sep10Config->getKnownCustodialAccountList() ?? [];
        $custodialWallet = in_array($request->account, $custodialAccountList);

        if ($custodialWallet && $request->clientDomain !== null) {
            $errorMsg = 'client_domain must not be specified if the account is an custodial-wallet account';

            return new JsonResponse(['error' => $errorMsg], 400);
        }

        if (!$custodialWallet && $this->sep10Config->isClientAttributionRequired()) {
            if ($request->clientDomain === null) {
                return new JsonResponse(['error' => 'client_domain is required'], 400);
            }
            $allowList = $this->sep10Config->getAllowedClientDomains() ?? [];
            if (!in_array($request->clientDomain, $allowList)) {
                // client_domain provided is not in the configured allow list
                return new JsonResponse(['error' => 'unable to process'], 403);
            }
        }

        return null;
    }

    public function validateChallengeRequestMemo(ChallengeRequest $request): Memo | ResponseInterface
    {
        // validate memo
        $memo = Memo::none();
        if ($request->memo !== null) {
            try {
                $value = intval($request->memo);
                if ($value < 0) {
                    return new JsonResponse(['error' => 'invalid memo value: ' . $value], 400);
                }
                $memo = Memo::id($value);
            } catch (Throwable $e) {
                return new JsonResponse(['error' => 'invalid memo value: ' . $request->memo], 400);
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
                $tomlData = TomlData::fromDomain($request->clientDomain, $httpClient);
                $clientSigningKey = $tomlData->generalInformation?->signingKey;
                if ($clientSigningKey === null) {
                    $msg = 'client signing key not found for domain: ' . $request->clientDomain;

                    return new JsonResponse(['error' => $msg], 400);
                }
            }

            $sep10SigningSeed = $this->secretConfig->getSep10SigningSeed();
            if (!isset($sep10SigningSeed)) {
                throw new InvalidSigningSeed('Invalid secret config: SEP-10 signing seed is not set');
            }
            $signer = KeyPair::fromSeed($sep10SigningSeed);
            $start = new DateTime('now');
            $end = new DateTime('now');
            $secondsToAdd = $this->sep10Config->getAuthTimeout();
            $end->modify("+$secondsToAdd seconds");
            $homeDomain = $request->homeDomain;
            if ($homeDomain === null) {
                $homeDomain = $this->sep10Config->getHomeDomains()[0];
            }
            $webDomain = $this->sep10Config->getWebAuthDomain();
            if ($webDomain === null) {
                $webDomain = $this->sep10Config->getHomeDomains()[0];
            }

            return $this->newChallenge(
                signer: $signer,
                network: $this->appConfig->getStellarNetwork(),
                clientAccountId: $request->account,
                domainName: $homeDomain,
                webAuthDomain: $webDomain,
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
        $builder = new ManageDataOperationBuilder($domainName . ' auth', random_bytes(64));
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
}
