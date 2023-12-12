<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep10;

use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;

use function is_string;

class ChallengeRequest
{
    /**
     * The Client Account, which can be a stellar account (G...) or muxed account (M...) that the Client wishes to authenticate with the Server.
     *
     * @var string client account id.
     */
    public string $account;
    /**
     * The memo to attach to the challenge transaction. Only permitted if a Stellar account (G...) is used.
     * The memo must be of type id. Other memo types are not supported.
     *
     * @var string|null The memo to attach to the challenge transaction.
     */
    public ?string $memo = null;

    /**
     * A Home Domain. Servers that generate tokens for multiple Home Domains can use this parameter to identify which
     * home domain the Client hopes to authenticate with. If not provided by the Client, the Server should assume
     * a default for backwards compatibility with older Clients.
     *
     * @var string|null The home domain.
     */
    public ?string $homeDomain = null;

    /**
     * A Client Domain. Supplied by Clients that intend to verify their domain in addition to the Client Account.
     * Servers should ignore this parameter if the Server does not support Client Domain verification,
     * or the Server does not support verification for the specific Client Domain included in the request.
     *
     * @var string|null The client domain.
     */
    public ?string $clientDomain = null;

    /**
     * @param string $account The Client Account, which can be a stellar account (G...) or muxed account (M...) that the Client wishes to authenticate with the Server.
     * @param string|null $memo The memo to attach to the challenge transaction. Only permitted if a Stellar account (G...) is used.
     * The memo must be of type id. Other memo types are not supported.
     * @param string|null $homeDomain A Home Domain. Servers that generate tokens for multiple Home Domains can use this parameter to identify which
     *  home domain the Client hopes to authenticate with. If not provided by the Client, the Server should assume
     *  a default for backwards compatibility with older Clients.
     * @param string|null $clientDomain A Client Domain. Supplied by Clients that intend to verify their domain in addition to the Client Account.
     *  Servers should ignore this parameter if the Server does not support Client Domain verification,
     *  or the Server does not support verification for the specific Client Domain included in the request.
     */
    public function __construct(
        string $account,
        ?string $memo = null,
        ?string $homeDomain = null,
        ?string $clientDomain = null,
    ) {
        $this->account = $account;
        $this->memo = $memo;
        $this->homeDomain = $homeDomain;
        $this->clientDomain = $clientDomain;
    }

    /**
     * Creates a ChallengeRequest object from the given query parameters array.
     *
     * @param array<array-key, mixed> $queryParameters the array to parse the data from.
     *
     * @return ChallengeRequest the parsed challenge request.
     *
     * @throws InvalidRequestData
     */
    public static function fromQueryParameters(array $queryParameters): ChallengeRequest
    {
        if (!isset($queryParameters['account'])) {
            throw new InvalidRequestData('Account is not set');
        }
        $account = $queryParameters['account'];
        if (!is_string($account)) {
            throw new InvalidRequestData('Invalid account. Must be string.');
        }

        $result = new ChallengeRequest($account);
        if (isset($queryParameters['memo'])) {
            $memo = $queryParameters['memo'];
            if (!is_string($memo)) {
                throw new InvalidRequestData('Invalid memo value. Must be string.');
            }
            $result->memo = $memo;
        }
        if (isset($queryParameters['home_domain'])) {
            $homeDomain = $queryParameters['home_domain'];
            if (!is_string($homeDomain)) {
                throw new InvalidRequestData('Invalid home_domain value. Must be string.');
            }
            $result->homeDomain = $homeDomain;
        }
        if (isset($queryParameters['client_domain'])) {
            $clientDomain = $queryParameters['client_domain'];
            if (!is_string($clientDomain)) {
                throw new InvalidRequestData('Invalid client_domain value. Must be string.');
            }
            $result->clientDomain = $clientDomain;
        }

        return $result;
    }
}
