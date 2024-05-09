<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\exception;

use Throwable;

/**
 * This Exception must be thrown if a transaction was not found for a given id.
 */
class Sep31TransactionNotFoundForId extends AnchorFailure
{
    public string $id;

    public function __construct(string $id, int $code = 0, ?Throwable $previous = null)
    {
        $this->id = $id;
        $message = 'transaction not found for id: ' . $id;
        parent::__construct($message, $code, $previous);
    }
}
