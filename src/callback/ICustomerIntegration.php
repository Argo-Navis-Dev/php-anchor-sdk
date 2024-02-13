<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\CustomerNotFoundForId;
use ArgoNavis\PhpAnchorSdk\exception\SepNotAuthorized;

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
     * @throws CustomerNotFoundForId For requests containing an id parameter value
     *  that does not exist.
     * @throws SepNotAuthorized For requests containing an id parameter value
     *  that does not match to the given account and memo from the jwt token.
     * @throws AnchorFailure if any other error happens.
     */
    public function getCustomer(GetCustomerRequest $request): GetCustomerResponse;

    /**
     * Puts a customer.
     *
     * @param PutCustomerRequest $request The request to upload a customer.
     *
     * @return PutCustomerResponse The PUT customer response.
     *
     * @throws CustomerNotFoundForId For requests containing an id parameter value
     *  that does not exist.
     * @throws SepNotAuthorized For requests containing an id parameter value
     *  that does not match to the given account and memo from the jwt token.
     * @throws AnchorFailure if any other error happens.
     */
    public function putCustomer(PutCustomerRequest $request): PutCustomerResponse;

    /**
     * Puts customer verification data.
     *
     * @param PutCustomerVerificationRequest $request the request to upload the verification data.
     *
     * @return GetCustomerResponse the response.
     *
     * @throws CustomerNotFoundForId if the customer was not found for the given id.
     * @throws SepNotAuthorized If the given customer 'id' parameter value
     * that does not match to the given account and memo from the jwt token.
     * @throws AnchorFailure if any other error happens.
     */
    public function putCustomerVerification(PutCustomerVerificationRequest $request): GetCustomerResponse;

    /**
     * Deletes a customer.
     *
     * @param string $id The id of the customer to be deleted.
     *
     * @throws CustomerNotFoundForId if the customer was not found for the given id.
     * @throws AnchorFailure if any other error happens.
     */
    public function deleteCustomer(string $id): void;

    /**
     * Sets the callback url for a customer.
     *
     * @param PutCustomerCallbackRequest $request the request to put the callback url.
     *
     * @throws CustomerNotFoundForId For requests containing an id parameter value
     *  that does not exist.
     * @throws SepNotAuthorized For requests containing an id parameter value
     *  that does not match to the given account and memo from the jwt token.
     * @throws AnchorFailure if any other error happens.
     */
    public function putCustomerCallback(PutCustomerCallbackRequest $request): void;
}
