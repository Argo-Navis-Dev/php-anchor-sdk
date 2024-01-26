<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class CustomerStatus
{
    /**
     * All required KYC fields have been accepted and the customer has been validated for the type passed.
     * It is possible for an accepted customer to move back to another status if the KYC provider determines it needs more info at a later date,
     * or if the customer shows up on a sanctions list.
     */
    public const ACCEPTED = 'ACCEPTED';

    /**
     * KYC process is in flight and client can check again in the future to see if any further info is needed.
     */
    public const PROCESSING = 'PROCESSING';

    /**
     * More info needs to be provided to finish KYC for this customer. The fields entry is required in this case.
     */
    public const NEEDS_INFO = 'NEEDS_INFO';

    /**
     * This customer's KYC has failed and will never succeed. The message must be supplied in this case.
     */
    public const REJECTED = 'REJECTED';
}
