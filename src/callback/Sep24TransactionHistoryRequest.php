<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use DateTime;

class Sep24TransactionHistoryRequest
{
    /**
     * @var string $assetCode The code of the asset of interest. E.g. BTC, ETH, USD, INR, native, etc.
     */
    public string $assetCode;

    /**
     * @var DateTime|null $noOlderThan The response should contain transactions starting on or after this date & time.
     */
    public ?DateTime $noOlderThan = null;

    /**
     * @var int|null $limit The response should contain at most limit transactions.
     */
    public ?int $limit = null;

    /**
     * @var string|null $kind The kind of transaction that is desired. Must be either 'deposit' or 'withdrawal'.
     */
    public ?string $kind = null;

    /**
     * @var string|null $pagingId The response should contain transactions starting prior to this ID (exclusive).
     */
    public ?string $pagingId = null;

    /**
     * @var string|null $lang Language code specified using RFC 4646.
     */
    public ?string $lang = null;

    /**
     * @param string $assetCode The code of the asset of interest. E.g. BTC, ETH, USD, INR, native, etc.
     * @param DateTime|null $noOlderThan The response should contain transactions starting on or after this date & time.
     * @param int|null $limit The response should contain at most limit transactions.
     * @param string|null $kind The kind of transaction that is desired. Must be either 'deposit' or 'withdrawal'.
     * @param string|null $pagingId The response should contain transactions starting prior to this ID (exclusive).
     * @param string|null $lang Language code specified using RFC 4646.
     */
    public function __construct(
        string $assetCode,
        ?DateTime $noOlderThan = null,
        ?int $limit = null,
        ?string $kind = null,
        ?string $pagingId = null,
        ?string $lang = null,
    ) {
        $this->assetCode = $assetCode;
        $this->noOlderThan = $noOlderThan;
        $this->limit = $limit;
        $this->kind = $kind;
        $this->pagingId = $pagingId;
        $this->lang = $lang;
    }
}
