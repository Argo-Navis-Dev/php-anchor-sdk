<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\callback\ICrossBorderPaymentsIntegration;
use ArgoNavis\PhpAnchorSdk\shared\Sep12TypesInfo;
use ArgoNavis\PhpAnchorSdk\shared\Sep31AssetInfo;

class CrossBorderPaymentsIntegration implements ICrossBorderPaymentsIntegration
{
    public const USDC = 'USDC';

    public const QUOTES_SUPPORTED = true;

    public const QUOTES_REQUIRED = false;

    public const FEE_FIXED = 5;
    public const FEE_PERCENT = 1;
    public const MIN_AMOUNT = 0.1;
    public const MAX_AMOUNT = 100;

    /**
     * Returns the supported assets.
     *
     * @return array<Sep31AssetInfo> The supported assets.
     */
    public function supportedAssets(string | null $lang): array
    {
        return self::composeSupportedAssets($lang);
    }

    /**
     * Composes the supported assets for testing.
     *
     * @return array<Sep31AssetInfo> The supported assets.
     */
    private static function composeSupportedAssets(string | null $lang): array
    {
        $usdcSender = [];
        $usdcSender['sep31-sender'] = [
            'description' => 'U.S. citizens limited to sending payments of less than $10,000 in value',
        ];
        $usdcSender['sep31-large-sender'] = ['description' => 'U.S. citizens that do not have sending limits'];
        $usdcSender['sep31-foreign-sender'] = [
            'description' => 'non-U.S. citizens sending payments of less than $10,000 in value',
        ];
        $usdcReceiver = [];
        $usdcReceiver['sep31-receiver'] = ['description' => 'U.S. citizens receiving USD'];
        $usdcReceiver['sep31-foreign-receiver'] = ['description' => 'non-U.S. citizens receiving USD'];
        $usdcSep12 = new Sep12TypesInfo($usdcSender, $usdcReceiver);
        $assetInfoUsdc = new Sep31AssetInfo(
            asset: self::USDC,
            quotesSupported: self::QUOTES_SUPPORTED,
            quotesRequired: self::QUOTES_REQUIRED,
            feeFixed: self::FEE_FIXED,
            feePercent: self::FEE_PERCENT,
            minAmount: self::MIN_AMOUNT,
            maxAmount: self::MAX_AMOUNT,
            sep12: $usdcSep12,
        );

        return [$assetInfoUsdc];
    }
}
