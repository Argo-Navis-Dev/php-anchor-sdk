<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\exception;

use Exception;
use Throwable;

class AccountNotFound extends Exception
{
    public string $accountId;

    public function __construct(string $accountId, int $code = 0, ?Throwable $previous = null)
    {
        $this->accountId = $accountId;
        $message = 'Account not found for id: ' . $accountId;
        parent::__construct($message, $code, $previous);
    }
}
