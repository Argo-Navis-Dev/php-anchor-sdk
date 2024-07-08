<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\Sep08\Sep08ResponseStatus;

/**
 * This response means that the transaction is not compliant and could not be revised to be made compliant.
 */
class ApprovalRejected
{
    /**
     * @var string $error A human-readable string explaining why the transaction is not compliant and
     * could not be made compliant.
     */
    public string $error;

    /**
     * Constructor.
     *
     * @param string $error A human-readable string explaining why the transaction is not compliant and
     *  could not be made compliant.
     */
    public function __construct(string $error)
    {
        $this->error = $error;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return ['status' => Sep08ResponseStatus::REJECTED, 'error' => $this->error];
    }
}
