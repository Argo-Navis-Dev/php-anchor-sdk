<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\config;

interface ISep24Config
{
    public function isAccountCreationSupported(): bool;

    public function areClaimableBalancesSupported(): bool;

    public function isFeeEndpointSupported(): bool;

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
