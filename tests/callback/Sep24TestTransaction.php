<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\callback\Sep24TransactionResponse;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;

class Sep24TestTransaction
{
    public IdentificationFormatAsset $asset;
    public Sep24TransactionResponse $data;
    public string $account;
    public ?string $accountMemo = null;

    public function __construct(
        IdentificationFormatAsset $asset,
        Sep24TransactionResponse $data,
        string $account,
        ?string $accountMemo = null,
    ) {
        $this->asset = $asset;
        $this->data = $data;
        $this->account = $account;
        $this->accountMemo = $accountMemo;
    }
}
