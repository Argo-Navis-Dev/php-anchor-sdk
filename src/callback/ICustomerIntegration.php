<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;

/**
 * The interface for the customer endpoint of the callback API.
 */
interface ICustomerIntegration
{
    /**
     * Gets a customer.
     *
     * @param GetCustomerRequest $request The request to get a customer.
     *
     * @return GetCustomerResponse The GET customer response.
     *
     * @throws AnchorFailure if error happens. For requests containing an id parameter value
     * that does not exist or exists for a customer created by another anchor throw CustomerNotFoundForId
     */
    public function getCustomer(GetCustomerRequest $request): GetCustomerResponse;

    /**
     * Puts a customer.
     *
     * @param PutCustomerRequest $request The request to upload a customer.
     *
     * @return PutCustomerResponse The PUT customer response.
     *
     * @throws AnchorFailure if error happens.
     */
    public function putCustomer(PutCustomerRequest $request): PutCustomerResponse;

    /**
     * Puts customer verification data.
     *
     * @param PutCustomerVerificationRequest $request the request to upload the verification data.
     *
     * @return GetCustomerResponse the response.
     *
     * @throws AnchorFailure if error happens.
     */
    public function putCustomerVerification(PutCustomerVerificationRequest $request): GetCustomerResponse;

    /**
     * Deletes a customer.
     *
     * @param string $id The id of the customer to be deleted.
     *
     * @throws AnchorFailure if error happens.
     */
    public function deleteCustomer(string $id): void;
}
