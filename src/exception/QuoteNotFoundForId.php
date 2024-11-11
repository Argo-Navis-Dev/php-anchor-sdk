<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\exception;

use Throwable;

/**
 * This Exception must be thrown if a quote was not found for a given id.
 */
class QuoteNotFoundForId extends AnchorFailure
{
    public string $id;

    /**
     * @param string $id The id of the quote.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     * @param string|null $messageKey The message key.
     * @param array<string,string>|null $messageParams The message parameters.
    */
    public function __construct(
        string $id,
        int $code = 0,
        ?Throwable $previous = null,
        ?string $messageKey = null,
        ?array $messageParams = [],
    ) {
        $this->id = $id;
        $message = 'quote not found for id: ' . $id;
        parent::__construct(
            message: $message,
            code: $code,
            messageKey: $messageKey,
            messageParams: $messageParams,
            previous: $previous,
        );
    }
}
