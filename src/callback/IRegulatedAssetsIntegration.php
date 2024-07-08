<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;

/**
 * The interface for the SEP-08 endpoints of the callback API.
 */
interface IRegulatedAssetsIntegration
{
    /**
     * @param string $tx The base64 encoded transaction envelope XDR signed by the user. This is the transaction that
     * must be tested for compliance and signed on success.
     *
     * @return ApprovalSuccess | ApprovalRevised | ApprovalPending | ApprovalActionRequired | ApprovalRejected as a possible
     * response for the compliance test.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function approve(
        string $tx,
    ): ApprovalSuccess | ApprovalRevised | ApprovalPending | ApprovalActionRequired | ApprovalRejected;
}
