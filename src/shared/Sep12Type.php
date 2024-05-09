<?php

declare(strict_types=1);

// Copyright 2024 The Stellar PHP SDK Authors. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

/**
 * SEP12 type used in SEP-31 to define the sender and receiver SEP-12 types. See SEP-38.
 */
class Sep12Type
{
    /**
     * @var string Type name. E.g. 'sep31-sender', 'sep31-large-sender', 'sep31-foreign-receiver'
     */
    public string $name;

    /**
     * @var string Human-readable description. E.g. 'U.S. citizens limited to sending payments of less than $10,000 in value'
     */
    public string $description;

    /**
     * Constructor.
     *
     * @param string $name Type name. E.g. 'sep31-sender', 'sep31-large-sender', 'sep31-foreign-receiver'
     * @param string $description Human-readable description. E.g. 'U.S. citizens limited to sending payments of less than $10,000 in value'
     */
    public function __construct(string $name, string $description)
    {
        $this->name = $name;
        $this->description = $description;
    }
}
