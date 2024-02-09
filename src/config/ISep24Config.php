<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\config;

interface ISep24Config
{
    /**
     * Whether or not the anchor supports creating accounts for users requesting deposits.
     *
     * @return bool true if anchor supports creating accounts for users requesting deposits.
     */
    public function isAccountCreationSupported(): bool;

    /**
     * Whether or not the anchor supports sending deposit funds as claimable balances.
     * This is relevant for users of Stellar accounts without a trustline to the requested asset.
     *
     * @return bool true if anchor supports sending deposit funds as claimable balances.
     */
    public function areClaimableBalancesSupported(): bool;

    /**
     * Whether or not the anchor supports the fee endpoint.
     *
     * @return bool anchor supports the fee endpoint.
     */
    public function isFeeEndpointSupported(): bool;

    /**
     * If the anchor supports the fee endpoint, this defines whether or not the fee endpoint
     * requires the user to be SEP-10 authenticated.
     *
     * @return bool true if the user must be SEP-10 authenticated to access the fee endpoint.
     */
    public function feeEndpointRequiresAuthentication(): bool;

    /**
     * Only relevant if fee endpoint is supported.
     * If this returns true, the sdk calculates the fee for those requests
     * that allow obvious fee calculation (feeFixed and/or feePercent is given in the asset info).
     *
     * @return bool true if the SDK should calculate obvious fees and not pass them to the integration->getFee() method.
     * false if also obvious fee calculations should be passed to the integration->getFee() method.
     */
    public function shouldSdkCalculateObviousFee(): bool;

    /**
     * @return int|null the maximum size of a file to be uploaded. If not set, it defaults to 2 MB.
     */
    public function getUploadFileMaxSizeMb(): ?int;

    /**
     * @return int|null the maximum number of allowed files to be uploaded. If not set, it defaults to 6.
     */
    public function getUploadFileMaxCount(): ?int;
}
