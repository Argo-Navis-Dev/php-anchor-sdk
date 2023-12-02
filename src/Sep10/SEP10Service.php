<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep10;

use ArgoNavis\PhpAnchorSdk\config\IAppConfig;
use ArgoNavis\PhpAnchorSdk\config\ISecretConfig;
use ArgoNavis\PhpAnchorSdk\config\ISep10Config;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSigningSeed;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soneso\StellarSDK\Crypto\KeyPair;
use Throwable;

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
        return new TextResponse('Not implemented', status: 501);
    }
}
