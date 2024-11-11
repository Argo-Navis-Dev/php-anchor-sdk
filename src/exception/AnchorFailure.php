<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\exception;

use Exception;
use Throwable;

/**
 * Base class for all Anchor exceptions.
 */
class AnchorFailure extends Exception
{
    private ?string $messageKey;
    /**
     * @var array<string, string>|null $messageParams
     */
    private ?array $messageParams;

    /**
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param string|null $messageKey The message key.
     * @param array<string,string>|null $messageParams The message parameters.
     * @param Throwable|null $previous The previous exception.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?string $messageKey = null,
        ?array $messageParams = [],
        ?Throwable $previous = null,
    ) {
        // Store the errorKey in the class property
        $this->messageKey = $messageKey;
        // Store the messageParams in the class property
        $this->messageParams = $messageParams;
        // Pass the message and code to the parent constructor
        parent::__construct($message, $code, $previous);
    }

    public function getMessageKey(): string | null
    {
        return $this->messageKey;
    }

    /**
     * @return array<string, string> | null
     */
    public function getMessageParams(): array | null
    {
        return $this->messageParams;
    }
}
