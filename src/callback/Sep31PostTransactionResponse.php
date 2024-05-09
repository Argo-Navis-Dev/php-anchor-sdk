<?php

declare(strict_types=1);

// Copyright 2024 The Stellar PHP SDK Authors. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

class Sep31PostTransactionResponse
{
    /**
     * @var string $id The persistent identifier to check the status of this payment transaction.
     */
    public string $id;

    /**
     * @var string|null $stellarAccountId (optional) The Stellar account to send payment to.
     */
    public ?string $stellarAccountId;

    /**
     * @var string|null $stellarMemoType (optional) The type of memo to attach to the Stellar payment (text, hash, or id).
     */
    public ?string $stellarMemoType;

    /**
     * @var string|null $stellarMemo (optional) The memo to attach to the Stellar payment.
     */
    public ?string $stellarMemo;

    /**
     * @param string $id The persistent identifier to check the status of this payment transaction.
     * @param string|null $stellarAccountId (optional) The Stellar account to send payment to.
     * @param string|null $stellarMemoType (optional) The type of memo to attach to the Stellar payment (text, hash, or id).
     * @param string|null $stellarMemo (optional) The memo to attach to the Stellar payment.
     */
    public function __construct(
        string $id,
        ?string $stellarAccountId,
        ?string $stellarMemoType,
        ?string $stellarMemo,
    ) {
        $this->id = $id;
        $this->stellarAccountId = $stellarAccountId;
        $this->stellarMemoType = $stellarMemoType;
        $this->stellarMemo = $stellarMemo;
    }

    /**
     * Converts the object to JSON representation.
     *
     * @return array<string, mixed> The JSON representation of the object.
     */
    public function toJson(): array
    {
        $json = [];
        $json['id'] = $this->id;
        if ($this->stellarAccountId !== null) {
            $json['stellar_account_id'] = $this->stellarAccountId;
        }
        if ($this->stellarMemoType !== null) {
            $json['stellar_memo_type'] = $this->stellarMemoType;
        }
        if ($this->stellarMemo !== null) {
            $json['stellar_memo'] = $this->stellarMemo;
        }

        return $json;
    }
}
