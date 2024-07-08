<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\Sep08\Sep08ResponseStatus;

/**
 * This response means that the issuer could not determine whether to approve the transaction at the time of
 * receiving it. Wallet can re-submit the same transaction at a later point in time.
 */
class ApprovalPending
{
    /**
     * @var int $timeout Number of milliseconds to wait before submitting the same transaction again. Use 0 if the wait time cannot be determined.
     */
    public int $timeout;
    /**
     * @var string|null $message (optional) A human-readable string containing information to pass on to the user.
     */
    public ?string $message = null;

    /**
     * @param int $timeout Number of milliseconds to wait before submitting the same transaction again. Use 0 if the wait time cannot be determined.
     * @param string|null $message (optional) A human-readable string containing information to pass on to the user.
     */
    public function __construct(int $timeout, ?string $message = null)
    {
        $this->timeout = $timeout;
        $this->message = $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $json
         */
        $json = [
            'status' => Sep08ResponseStatus::PENDING,
            'timeout' => $this->timeout,
        ];

        if ($this->message !== null) {
            $json += ['message' => $this->message];
        }

        return $json;
    }
}
