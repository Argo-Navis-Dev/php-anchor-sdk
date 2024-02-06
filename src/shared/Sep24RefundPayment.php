<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep24RefundPayment
{
    /**
     * @var string $id The payment ID that can be used to identify the refund payment. This is either a Stellar transaction hash or an off-chain payment identifier, such as a reference number provided to the user when the refund was initiated. This id is not guaranteed to be unique.
     */
    public string $id;

    /**
     * @var string $idType Type of the id. Possible values: 'stellar' or 'extern'
     */
    public string $idType;

    /**
     * @var string The amount sent back to the user for the payment identified by id, in units of amountInAsset.
     */
    public string $amount;

    /**
     * @var string $fee The amount charged as a fee for processing the refund, in units of amountInAsset.
     */
    public string $fee;

    /**
     * @param string $id The payment ID that can be used to identify the refund payment. This is either a Stellar transaction hash or an off-chain payment identifier, such as a reference number provided to the user when the refund was initiated. This id is not guaranteed to be unique.
     * @param string $idType Type of the id. Possible values: 'stellar' or 'extern'
     * @param string $amount The amount sent back to the user for the payment identified by id, in units of amountInAsset.
     * @param string $fee The amount charged as a fee for processing the refund, in units of amountInAsset.
     */
    public function __construct(string $id, string $idType, string $amount, string $fee)
    {
        $this->id = $id;
        $this->idType = $idType;
        $this->amount = $amount;
        $this->fee = $fee;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'id' => $this->id,
            'id_type' => $this->idType,
            'amount' => $this->amount,
            'fee' => $this->fee,
        ];
    }
}
