<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Stellar;

use ArgoNavis\PhpAnchorSdk\exception\AccountNotFound;
use Soneso\StellarSDK\Exceptions\HorizonRequestException;
use Soneso\StellarSDK\StellarSDK;

class TrustlinesHelper
{
    /**
     * Checks if the Stellar account given by accountId trusts the Stellar asset given by assetCode and assetIssuer.
     * Uses the horizon instance defined by horizonUrl to query the data.
     *
     * @param string $horizonUrl This is the base url to the Stellar Horizon instance that should be used to check if
     * the given Stellar account trust the given anchor asset.
     * @param string $accountId The account id of the Stellar account to check if it trusts the asset.
     * @param string $assetCode the asset code of the asset to check if the account trusts.
     * @param string $assetIssuer the asset issuer id of the asset to check if the account trusts.
     *
     * @return bool true if the account trusts the asset. Otherwise, false.
     *
     * @throws AccountNotFound if the account given by account id does not exist on the Stellar Network.
     * @throws HorizonRequestException if any other horizon error occurred.
     */
    public static function checkIfAccountTrustsAsset(
        string $horizonUrl,
        string $accountId,
        string $assetCode,
        string $assetIssuer,
    ): bool {
        $sdk = new StellarSDK($horizonUrl);
        try {
            $account = $sdk->requestAccount($accountId);

            foreach ($account->getBalances() as $balance) {
                if ($balance->getAssetCode() === $assetCode && $balance->getAssetIssuer() === $assetIssuer) {
                    return true;
                }
            }

            return false;
        } catch (HorizonRequestException $e) {
            if ($e->getStatusCode() === 404) {
                throw new AccountNotFound($accountId);
            }

            throw $e;
        }
    }
}
