<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep06;

use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\shared\Sep06AssetInfo;

use function implode;
use function in_array;
use function strval;

class Sep06RequestValidator
{
    /**
     * Checks if the asset for the given asset code is supported and enabled for deposit operations.
     *
     * @param string $assetCode the asset code of the asset to check.
     * @param array<Sep06AssetInfo> $supportedAssets the list of supported assets.
     *
     * @return Sep06AssetInfo the asset if found and enabled for deposit.
     *
     * @throws InvalidSepRequest if the asset was not found or not enabled.
     */
    public static function getDepositAsset(string $assetCode, array $supportedAssets): Sep06AssetInfo
    {
        $depositAsset = null;
        foreach ($supportedAssets as $supportedAsset) {
            if ($supportedAsset->asset->getCode() === $assetCode) {
                if ($supportedAsset->depositOperation->enabled) {
                    $depositAsset = $supportedAsset;

                    break;
                }
            }
        }
        if ($depositAsset === null) {
            throw new InvalidSepRequest('invalid operation for asset ' . $assetCode);
        }

        return $depositAsset;
    }

    /**
     * Checks if the asset for the given asset code is supported and enabled for withdrawal operations.
     *
     * @param string $assetCode the asset code of the asset to check.
     * @param array<Sep06AssetInfo> $supportedAssets the list of supported assets.
     *
     * @return Sep06AssetInfo the asset if found and enabled for withdrawal.
     *
     * @throws InvalidSepRequest if the asset was not found or not enabled.
     */
    public static function getWithdrawAsset(string $assetCode, array $supportedAssets): Sep06AssetInfo
    {
        $withdrawAsset = null;
        foreach ($supportedAssets as $supportedAsset) {
            if ($supportedAsset->asset->getCode() === $assetCode) {
                if ($supportedAsset->withdrawOperation->enabled) {
                    $withdrawAsset = $supportedAsset;

                    break;
                }
            }
        }
        if ($withdrawAsset === null) {
            throw new InvalidSepRequest('invalid operation for asset ' . $assetCode);
        }

        return $withdrawAsset;
    }

    /**
     * Checks if the asset for the given asset code is supported and enabled for deposit exchange operations.
     *
     * @param string $assetCode the asset code of the asset to check.
     * @param array<Sep06AssetInfo> $supportedAssets the list of supported assets.
     *
     * @return Sep06AssetInfo the asset if found and enabled for deposit exchange.
     *
     * @throws InvalidSepRequest if the asset was not found or not enabled.
     */
    public static function getDestinationAsset(string $assetCode, array $supportedAssets): Sep06AssetInfo
    {
        $destinationAsset = null;
        foreach ($supportedAssets as $supportedAsset) {
            if ($supportedAsset->asset->getCode() === $assetCode) {
                if ($supportedAsset->depositOperation->enabled && $supportedAsset->depositExchangeEnabled) {
                    $destinationAsset = $supportedAsset;

                    break;
                }
            }
        }
        if ($destinationAsset === null) {
            throw new InvalidSepRequest('invalid operation for asset ' . $assetCode);
        }

        return $destinationAsset;
    }

    /**
     * Checks if the asset for the given asset code is supported and enabled for withdraw exchange operations.
     *
     * @param string $assetCode the asset code of the asset to check.
     * @param array<Sep06AssetInfo> $supportedAssets the list of supported assets.
     *
     * @return Sep06AssetInfo the asset if found and enabled for withdraw exchange.
     *
     * @throws InvalidSepRequest if the asset was not found or not enabled.
     */
    public static function getSourceAsset(string $assetCode, array $supportedAssets): Sep06AssetInfo
    {
        $sourceAsset = null;
        foreach ($supportedAssets as $supportedAsset) {
            if ($supportedAsset->asset->getCode() === $assetCode) {
                if ($supportedAsset->withdrawOperation->enabled && $supportedAsset->withdrawExchangeEnabled) {
                    $sourceAsset = $supportedAsset;

                    break;
                }
            }
        }
        if ($sourceAsset === null) {
            throw new InvalidSepRequest('invalid operation for asset ' . $sourceAsset);
        }

        return $sourceAsset;
    }

    /**
     * Validates that the requested deposit/withdrawal type is valid.
     *
     * @param String $requestType the requested type
     * @param string $assetCode the requested asset code
     * @param array<string> $validTypes the valid types
     *
     * @return void if valid.
     *
     * @throws InvalidSepRequest if the type is invalid
     */
    public static function validateType(string $requestType, string $assetCode, array $validTypes): void
    {
        if (!in_array($requestType, $validTypes)) {
            throw new InvalidSepRequest('Invalid type ' .
                $requestType . ' for asset ' . $assetCode .
                '. Supported types are ' . implode(', ', $validTypes) . '.');
        }
    }

    /**
     * Validates that the requested amount is within bounds.
     *
     * @param float $requestAmount the requested amount
     * @param string $assetCode the requested asset code
     * @param float|null $minAmount the minimum amount
     * @param float|null $maxAmount the maximum amount
     *
     * @return void if valid.
     *
     * @throws InvalidSepRequest if the amount is not within bounds
     */
    public static function validateAmount(
        float $requestAmount,
        string $assetCode,
        ?float $minAmount = null,
        ?float $maxAmount = null,
    ): void {
        if (
            $requestAmount <= 0 ||
            ($minAmount !== null && $requestAmount < $minAmount) ||
            ($maxAmount !== null && $requestAmount > $maxAmount)
        ) {
            throw new InvalidSepRequest('invalid amount ' . strval($requestAmount) .
                ' for asset ' . $assetCode);
        }
    }
}
