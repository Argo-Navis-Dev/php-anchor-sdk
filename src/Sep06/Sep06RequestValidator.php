<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep06;

use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use ArgoNavis\PhpAnchorSdk\shared\Sep06AssetInfo;
use Psr\Log\LoggerInterface;

use function implode;
use function in_array;
use function strval;

class Sep06RequestValidator
{
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

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
        self::getLogger()->debug(
            'Searching asset in supported assets.',
            ['context' => 'sep06', 'asset_code' => $assetCode, 'operation' => 'deposit'],
        );
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
            self::getLogger()->error(
                'Deposit asset not found, invalid operation for asset.',
                ['context' => 'sep06', 'asset_code' => $assetCode, 'operation' => 'deposit'],
            );

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
        self::getLogger()->debug(
            'Searching asset in supported assets.',
            ['context' => 'sep06', 'asset_code' => $assetCode, 'operation' => 'withdraw'],
        );
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
            self::getLogger()->error(
                'Withdraw asset not found, invalid operation for asset.',
                ['context' => 'sep06', 'asset_code' => $assetCode, 'operation' => 'withdraw'],
            );

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
        self::getLogger()->debug(
            'Searching asset (destination) in supported assets.',
            ['context' => 'sep06', 'asset_code' => $assetCode],
        );
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
            self::getLogger()->error(
                'Destination asset not found, invalid operation for asset.',
                ['context' => 'sep06', 'asset_code' => $assetCode],
            );

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
        self::getLogger()->debug(
            'Searching asset (source) in supported assets.',
            ['context' => 'sep06', 'asset_code' => $assetCode],
        );
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
            self::getLogger()->error(
                'Source asset not found, invalid operation for asset.',
                ['context' => 'sep06', 'asset_code' => $assetCode],
            );

            throw new InvalidSepRequest('invalid operation for asset null');
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
            self::getLogger()->error(
                'Invalid type.',
                ['context' => 'sep06', 'request_type' => $requestType,
                    'asset_code' => $assetCode, 'valid_types' => implode(', ', $validTypes),
                ],
            );

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
            self::getLogger()->error(
                'Invalid amount.',
                ['context' => 'sep06', 'request_amount' => $requestAmount, 'for_asset' => $assetCode],
            );

            throw new InvalidSepRequest('invalid amount ' . strval($requestAmount) .
                ' for asset ' . $assetCode);
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
