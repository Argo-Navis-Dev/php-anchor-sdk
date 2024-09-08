<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Stellar;

class ReceivedPaymentsQueryResult
{
    /**
     * @var string $cursor The (start) cursor used to query the transactions for the receiver account.
     */
    public string $cursor;
    /**
     * @var string $receiverAccountId The id of the Stellar account that received the payments.
     */
    public string $receiverAccountId;
    /**
     * @var array<ReceivedPayment> $receivedPayments The found received payments of the query.
     */
    public array $receivedPayments;

    /**
     * @var string $lastTransactionPagingToken The last transaction paging token from the query results.
     * To be used as a cursor in successive queries.
     */
    public string $lastTransactionPagingToken;

    /**
     * Constructor.
     *
     * @param string $cursor the (start) cursor used to query the receiver account for transactions.
     * @param string $receiverAccountId the id of the account that received the payments.
     * @param array<ReceivedPayment> $receivedPayments the found received payments of the query.
     * @param string $lastTransactionPagingToken the last transaction paging token from the query results.
     *  To be used as a cursor in successive queries.
     */
    public function __construct(
        string $cursor,
        string $receiverAccountId,
        array $receivedPayments,
        string $lastTransactionPagingToken,
    ) {
        $this->cursor = $cursor;
        $this->receiverAccountId = $receiverAccountId;
        $this->receivedPayments = $receivedPayments;
        $this->lastTransactionPagingToken = $lastTransactionPagingToken;
    }
}
