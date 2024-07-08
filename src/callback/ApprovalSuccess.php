<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\Sep08\Sep08ResponseStatus;

/**
 * This response means that the transaction was found compliant and signed without being revised.
 */
class ApprovalSuccess
{
    /**
     * @var string $tx Transaction envelope XDR, base64 encoded. This transaction will have both the original signature(s)
     * from the request as well as one or multiple additional signatures from the issuer.
     */
    public string $tx;

    /**
     * @var string|null $message (optional) A human-readable string containing information to pass on to the user.
     */
    public ?string $message = null;

    /**
     * @param string $tx Transaction envelope XDR, base64 encoded. This transaction will have both the original signature(s)
     *  from the request as well as one or multiple additional signatures from the issuer.
     * @param string|null $message (optional) A human-readable string containing information to pass on to the user.
     */
    public function __construct(string $tx, ?string $message = null)
    {
        $this->tx = $tx;
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
            'status' => Sep08ResponseStatus::SUCCESS,
            'tx' => $this->tx,
        ];

        if ($this->message !== null) {
            $json += ['message' => $this->message];
        }

        return $json;
    }
}
