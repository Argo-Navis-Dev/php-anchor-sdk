<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

class Sep31PutTransactionCallbackRequest
{
    /**
     * @var string $transactionId id of the transaction to put the callback for.
     */
    public string $transactionId;

    /**
     * @var string $accountId account id of the sending anchor received via SEP-10.
     */
    public string $accountId;

    /**
     * @var string|null $accountMemo account memo of the sending anchor received via SEP-10.
     */
    public ?string $accountMemo = null;

    /**
     * @var string $url a callback URL that the Receiving Anchor will make application/json POST requests
     * to containing the transaction object defined in the response to GET /transaction/:id whenever the transaction's status value has changed
     */
    public string $url;

    /**
     * Constructor.
     *
     * @param string $transactionId id of the transaction to put the callback for.
     * @param string $accountId account id from SEP-10.
     * @param string|null $accountMemo account memo from SEP10 if any.
     * @param string $url The url to set as a callback url for the customer.
     */
    public function __construct(string $transactionId, string $accountId, ?string $accountMemo, string $url)
    {
        $this->transactionId = $transactionId;
        $this->accountId = $accountId;
        $this->accountMemo = $accountMemo;
        $this->url = $url;
    }
}
