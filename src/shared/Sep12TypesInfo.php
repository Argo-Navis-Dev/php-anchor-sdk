<?php

declare(strict_types=1);

// Copyright 2024 The Stellar PHP SDK Authors. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep12TypesInfo
{
    /**
     * An object containing a types key if KYC information is required for the Sending Client, empty otherwise.
     *
     * @var array<string, mixed> The sender types.
     */
    public array $senderTypes;

    /**
     * An object containing a types key if KYC information is required for the Receiving Client, empty otherwise.
     *
     * @var array<string, mixed> The receiver types.
     */
    public array $receiverTypes;

    /**
     * @param array<string, mixed> $senderTypes The sender types.
     * @param array<string, mixed> $receiverTypes The receiver types.
     */
    public function __construct(array $senderTypes, array $receiverTypes)
    {
        $this->senderTypes = $senderTypes;
        $this->receiverTypes = $receiverTypes;
    }

    /**
     * Converts the object to an array.
     *
     * @return array<string, mixed> The array representation of the object.
     */
    public function toJson(): array
    {
        $result = [];
        //Prepare the sender data
        $result['sender'] = ['types' => $this->senderTypes];

        //Prepare the receiver data
        $result['receiver'] = ['types' => $this->receiverTypes];

        return $result;
    }
}
