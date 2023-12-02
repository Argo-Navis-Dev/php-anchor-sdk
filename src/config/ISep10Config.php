<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\config;

interface ISep10Config
{
    /**
     * The `web_auth_domain` property of <a
     * href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0010.md#response">SEP-10</a>.
     * If the `web_auth_domain` is not specified, the `web_auth_domain` will be set to the first value
     * of `home_domains`. The `web_auth_domain` value must equal to the host of the SEP server.
     *
     * @return ?string the web auth domain.
     */
    public function getWebAuthDomain(): ?string;

    /**
     *  The `home_domains` property of <a
     *  href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0010.md#request">SEP-10</a>.
     *
     * @return array<string>
     */
    public function getHomeDomains(): array;

    /**
     * Set the authentication challenge transaction timeout in seconds. An expired signed transaction
     * will be rejected. This is the timeout period the client must finish the authentication process.
     * (ie: sign and respond the challenge transaction).
     *
     * @return int auth timeout in seconds.
     */
    public function getAuthTimeout(): int;

    /**
     *  Set the timeout in seconds of the authenticated JSON Web Token. An expired JWT will be
     *  rejected. This is the timeout period after the client has authenticated.
     *
     * @return int jwt token timeout in seconds.
     */
    public function getJwtTimeout(): int;

    /**
     *  Set if the client attribution is required. Client Attribution requires clients to verify their
     *  identity by passing a domain in the challenge transaction request and signing the challenge
     *  with the ``SIGNING_KEY`` on that domain's SEP-1 stellar.toml. See the SEP-10 section `Verifying
     *  Client Application Identity` for more information (<a
     *  href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0010.md#verifying-client-application-identity">SEP-10</a>).
     *  # # If the client_attribution_required is set to true, the list of allowed clients must be
     *  configured in the `clients` # section of this configuration file. The `domain` field of the
     *  client must be provided.
     *
     *  <p>The flag is only relevant for noncustodial wallets.
     *
     * @return bool true if client attribution is required.
     */
    public function isClientAttributionRequired(): bool;

    /**
     * Get the list of allowed client domains if client attribution is required.
     *
     * @return array<string>|null the list of allowed client domains.
     */
    public function getAllowedClientDomains(): ?array;

    /**
     * Set the list of known custodial accounts.
     *
     * @return array<string>|null
     */
    public function getKnownCustodialAccountList(): ?array;
}
