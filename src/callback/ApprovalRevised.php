<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\Sep08\Sep08ResponseStatus;

/**
 * This response means that the transaction was revised to be made compliant.
 */
class ApprovalRevised
{
    /**
     * @var string $tx Transaction envelope XDR, base64 encoded. This transaction is a revised compliant version of
     * the original request transaction, signed by the issuer.
     */
    public string $tx;

    /**
     * @var string $message A human-readable string explaining the modifications made to the transaction
     * to make it compliant.
     */
    public string $message;

    /**
     * Constructor.
     *
     * @param string $tx Transaction envelope XDR, base64 encoded. This transaction is a revised compliant version of
     *  the original request transaction, signed by the issuer.
     * @param string $message A human-readable string explaining the modifications made to the transaction
     *  to make it compliant.
     */
    public function __construct(string $tx, string $message)
    {
        $this->tx = $tx;
        $this->message = $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return ['status' => Sep08ResponseStatus::REVISED, 'tx' => $this->tx, 'message' => $this->message];
    }
}
