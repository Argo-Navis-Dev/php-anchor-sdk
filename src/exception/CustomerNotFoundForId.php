<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\exception;

use Throwable;

use function count;
use function is_array;

/**
 * This Exception must be thrown if a customer was not found for a given id.
 */
class CustomerNotFoundForId extends AnchorFailure
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
        ?string $messageKey = 'sep12_lang.error.customer_not_found',
        ?array $messageParams = [],
    ) {
        $this->id = $id;
        $message = 'customer not found for id: ' . $id;
        if (is_array($messageParams) && count($messageParams) === 0) {
            $messageParams = ['id' => $id];
        }
        parent::__construct(
            message: $message,
            code: $code,
            messageKey: $messageKey,
            messageParams: $messageParams,
            previous: $previous,
        );
    }
}
