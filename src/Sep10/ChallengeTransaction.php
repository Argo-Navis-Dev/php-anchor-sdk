<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep10;

use Soneso\StellarSDK\Transaction;

class ChallengeTransaction
{
    public Transaction $transaction;
    public string $clientAccountId;

    public string $matchedHomeDomain;

    public ?ClientDomainData $clientDomainData = null;

    /**
     * @param Transaction $transaction transaction.
     * @param string $clientAccountId account id of the client (user).
     * @param string $matchedHomeDomain matched home domain of the client.
     * @param ClientDomainData|null $clientDomainData client domain data (domain + account) if any.
     */
    public function __construct(
        Transaction $transaction,
        string $clientAccountId,
        string $matchedHomeDomain,
        ?ClientDomainData $clientDomainData = null,
    ) {
        $this->transaction = $transaction;
        $this->clientAccountId = $clientAccountId;
        $this->matchedHomeDomain = $matchedHomeDomain;
        $this->clientDomainData = $clientDomainData;
    }
}
