<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep10;

use Firebase\JWT\JWT;
use Soneso\StellarSDK\MuxedAccount;
use Throwable;

use function count;
use function explode;

class Sep10Jwt
{
    public string $jti; // JWT ID
    public string $iss; // Issuer
    public string $sub; // Subject
    public string $iat; // Issued At
    public string $exp; // Expiration Time


    public ?string $accountId = null;

    public ?string $homeDomain = null;
    public ?string $clientDomain = null;

    public ?string $accountMemo = null;

    public ?string $muxedAccountId = null;

    public ?int $muxedId = null;

    /**
     * Constructor.
     *
     * @param string $iss Issuer
     * @param string $sub should contain "accountId:memo" or "muxedAccountId" (starting with M)
     * @param string $iat Issued At
     * @param string $exp Expires At
     * @param string $jti Transaction id/Hash
     * @param string|null $homeDomain home domain if different to issuer.
     * @param string|null $clientDomain client domain if available.
     */
    public function __construct(
        string $iss,
        string $sub,
        string $iat,
        string $exp,
        string $jti,
        ?string $homeDomain = null,
        ?string $clientDomain = null,
    ) {
        $this->jti = $jti;
        $this->iss = $iss;
        $this->sub = $sub;
        $this->iat = $iat;
        $this->exp = $exp;
        $this->homeDomain = $homeDomain;
        $this->clientDomain = $clientDomain;
        $subs = explode(':', $sub);
        if (count($subs) === 2) {
            $this->accountId = $subs[0];
            $this->accountMemo = $subs[1];
        } else {
            try {
                $muxedAccount = MuxedAccount::fromMed25519AccountId($sub);
                $this->muxedAccountId = $sub;
                $this->accountId = $muxedAccount->getEd25519AccountId();
                $this->muxedId = $muxedAccount->getId();
            } catch (Throwable) {
            }
        }
    }

    /**
     * Returns the payload for the jwt token
     *
     * @return array<string,string>
     */
    public function getPayload(): array
    {
        $payload = [
            'jti' => $this->jti,
            'iss' => $this->iss,
            'sub' => $this->sub,
            'iat' => $this->iat,
            'exp' => $this->exp,
        ];
        if ($this->clientDomain !== null) {
            $payload['client_domain'] = $this->clientDomain;
        }
        if ($this->homeDomain !== null) {
            $payload['home_domain'] = $this->homeDomain;
        }

        return $payload;
    }

    /**
     * Signs and composes the jwt string.
     *
     * @param string $key The $key to sign with
     *
     * @return string The signed jwt token as string.
     */
    public function sign(string $key): string
    {
        return JWT::encode($this->getPayload(), $key, 'HS256');
    }
}
