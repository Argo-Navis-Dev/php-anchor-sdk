<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

/**
 * The response body of the PUT /customer endpoint.
 *
 * See: <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#customer-put">Customer PUT</a>.
 */
class PutCustomerResponse
{
    /**
     * @var string An identifier for the updated or created customer. E.g. "391fb415-c223-4608-b2f5-dd1e91e3a986"
     */
    public string $id;

    /**
     * @param string $id An identifier for the updated or created customer. E.g. "391fb415-c223-4608-b2f5-dd1e91e3a986"
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return array<string, string>
     */
    public function toJson(): array
    {
        return ['id' => $this->id];
    }
}
