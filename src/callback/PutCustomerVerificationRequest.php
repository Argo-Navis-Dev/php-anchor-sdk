<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

/**
 * The request body of the PUT /customer/verification endpoint of SEP-12.
 *
 * See: <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#customer-put-verification">Customer PUT Verification</a>.
 */
class PutCustomerVerificationRequest
{
    /**
     * @var string The ID of the customer as returned in the response of a previous PUT request.
     */
    public string $id;

    /**
     * @var array<string, string> One or more <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a> fields appended with _verification. E.g. "mobile_number_verification": "2735021"
     */
    public array $verificationFields = [];

    /**
     * @param string $id The ID of the customer as returned in the response of a previous PUT request.
     * @param array<string, string> $verificationFields One or more <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a> fields appended with _verification. E.g. "mobile_number_verification": "2735021".
     */
    public function __construct(string $id, array $verificationFields = [])
    {
        $this->id = $id;
        $this->verificationFields = $verificationFields;
    }
}
