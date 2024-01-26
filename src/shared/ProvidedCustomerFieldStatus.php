<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class ProvidedCustomerFieldStatus
{
    /**
     * The field has been validated. When all required fields are accepted, the Customer Status should also be accepted.
     */
    public const ACCEPTED = 'ACCEPTED';

    /**
     * The field is being validated. The client can make GET /customer requests to check on the result of this validation in the future.
     */
    public const PROCESSING = 'PROCESSING';

    /**
     * The field was in the PROCESSING status but did not pass validation. If the client may resubmit this field, the Customer Status should be NEEDS_INFO, otherwise it should be REJECTED.
     */
    public const REJECTED = 'REJECTED';

    /**
     * The field must be verified using the PUT /customer/verification endpoint.
     * For example, the mobile_number field could be placed in this status until a confirmation code
     * is sent to the customer and passed back to this endpoint.
     */
    public const VERIFICATION_REQUIRED = 'VERIFICATION_REQUIRED';
}
