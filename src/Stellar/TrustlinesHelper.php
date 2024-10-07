<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Stellar;

use ArgoNavis\PhpAnchorSdk\exception\AccountNotFound;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\Exceptions\HorizonRequestException;
use Soneso\StellarSDK\StellarSDK;

class TrustlinesHelper
{
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

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
                    self::getLogger()->debug(
                        'Account trust asset.',
                        ['context' => 'stellar', 'account_id' => $accountId,
                            'asset_code' => $assetCode, 'asset_issuer' => $assetIssuer, 'horizon_url' => $horizonUrl,
                        ],
                    );

                    return true;
                }
            }

            return false;
        } catch (HorizonRequestException $e) {
            self::getLogger()->error(
                'Failed to check if account trust asset.',
                ['context' => 'stellar', 'error' => $e->getMessage(), 'exception' => $e],
            );
            if ($e->getStatusCode() === 404) {
                throw new AccountNotFound($accountId);
            }

            throw $e;
        }
    }

    /**
     * Sets the logger in static context.
     */
    public static function setLogger(?LoggerInterface $logger = null): void
    {
        self::$logger = $logger ?? new NullLogger();
    }

    /**
     * Returns the logger (initializes if null).
     */
    private static function getLogger(): LoggerInterface
    {
        if (!isset(self::$logger)) {
            self::$logger = new NullLogger();
        }

        return self::$logger;
    }
}
