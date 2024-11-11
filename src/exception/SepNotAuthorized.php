<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\exception;

use Throwable;

class SepNotAuthorized extends SepHandlingFailure
{
    /**
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     * @param string|null $messageKey The message key.
     * @param array<string,string>|null $messageParams The message key parameters.
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        ?string $messageKey = 'shared_lang.error.unauthorized',
        ?array $messageParams = [],
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            messageKey: $messageKey,
            messageParams: $messageParams,
            previous: $previous,
        );
    }
}
