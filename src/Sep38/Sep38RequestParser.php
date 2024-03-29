<?php

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

declare(strict_types=1);

namespace ArgoNavis\PhpAnchorSdk\Sep38;

use ArgoNavis\PhpAnchorSdk\callback\Sep38PriceRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep38PricesRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep38QuoteRequest;
use ArgoNavis\PhpAnchorSdk\exception\InvalidAsset;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep38AssetInfo;
use ArgoNavis\PhpAnchorSdk\shared\Sep38DeliveryMethod;
use DateTime;

use function count;
use function floatval;
use function implode;
use function in_array;
use function is_numeric;
use function is_string;
use function strval;
use function trim;

use const DATE_ATOM;

class Sep38RequestParser
{
    /**
     * Validates the given request data and creates a Sep38PricesRequest from it.
     *
     * @param array<array-key, mixed> $requestData the get transactions request data to validate and use.
     * @param array<Sep38AssetInfo> $supportedAssets
     *
     * @return Sep38PricesRequest the validated request data.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getPricesRequestFromRequestData(
        array $requestData,
        array $supportedAssets,
    ): Sep38PricesRequest {
        // check sell asset
        $sellAssetInfo = self::getValidatedAsset(
            requestData: $requestData,
            supportedAssets: $supportedAssets,
            fieldKey: 'sell_asset',
        );

        // check sell amount
        $sellAmount = self::getValidatedAmount(requestData: $requestData, fieldKey: 'sell_amount');
        if ($sellAmount === null) {
            throw new InvalidSepRequest('sell_amount is required');
        }

        // construct request
        $pricesRequest = new Sep38PricesRequest(sellAsset: $sellAssetInfo->asset, sellAmount: strval($sellAmount));

        // check sell delivery method
        $pricesRequest->sellDeliveryMethod = self::getValidatedDeliveryMethod(
            requestData: $requestData,
            fieldKey: 'sell_delivery_method',
            supportedDeliveryMethods: $sellAssetInfo->sellDeliveryMethods,
        );

        // check country code
        $pricesRequest->countryCode = self::getValidatedCountryCode(
            requestData: $requestData,
            sellAssetInfo: $sellAssetInfo,
        );

        return $pricesRequest;
    }

    /**
     * Validates the given request data and creates a Sep38PriceRequest from it.
     *
     * @param array<array-key, mixed> $requestData the get transactions request data to validate and use.
     * @param array<Sep38AssetInfo> $supportedAssets
     *
     * @return Sep38PriceRequest the validated request data.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getPriceRequestFromRequestData(
        array $requestData,
        array $supportedAssets,
    ): Sep38PriceRequest {
        // check sell asset
        $sellAssetInfo = self::getValidatedAsset(
            requestData: $requestData,
            supportedAssets: $supportedAssets,
            fieldKey: 'sell_asset',
        );

        // check buy asset
        $buyAssetInfo = self::getValidatedAsset(
            requestData: $requestData,
            supportedAssets: $supportedAssets,
            fieldKey: 'buy_asset',
        );

        // check sell amount
        $sellAmount = self::getValidatedAmount(requestData: $requestData, fieldKey: 'sell_amount');

        // check buy amount
        $buyAmount = self::getValidatedAmount(requestData: $requestData, fieldKey: 'buy_amount');

        if (($sellAmount !== null && $buyAmount !== null) || ($sellAmount === null && $buyAmount === null)) {
            throw new InvalidSepRequest('either sell_amount or buy_amount must be provided, but not both.');
        }

        // check context
        $context = self::getValidatedContext(requestData: $requestData, allowedValues: ['sep6', 'sep31']);

        // construct request
        $priceRequest = new Sep38PriceRequest(
            context: $context,
            sellAsset: $sellAssetInfo->asset,
            buyAsset: $buyAssetInfo->asset,
            sellAmount: $sellAmount,
            buyAmount: $buyAmount,
        );

        // check sell delivery method
        $priceRequest->sellDeliveryMethod = self::getValidatedDeliveryMethod(
            requestData: $requestData,
            fieldKey: 'sell_delivery_method',
            supportedDeliveryMethods: $sellAssetInfo->sellDeliveryMethods,
        );

        // check buy delivery method
        $priceRequest->buyDeliveryMethod = self::getValidatedDeliveryMethod(
            requestData: $requestData,
            fieldKey: 'buy_delivery_method',
            supportedDeliveryMethods: $buyAssetInfo->buyDeliveryMethods,
        );

        // check country code
        $priceRequest->countryCode = self::getValidatedCountryCode(
            requestData: $requestData,
            sellAssetInfo: $sellAssetInfo,
            buyAssetInfo: $buyAssetInfo,
        );

        // check SEP-31 send limits as soon as SEP-31 is implemented.

        return $priceRequest;
    }

    /**
     * Validates the given request data and creates a Sep38PriceRequest from it.
     *
     * @param array<array-key, mixed> $requestData the get transactions request data to validate and use.
     * @param array<Sep38AssetInfo> $supportedAssets
     * @param string $accountId account id of the user authenticated with SEP-10
     * @param string|null $accountMemo account memo of the user authenticated with SEP-10
     *
     * @return Sep38QuoteRequest the validated request data.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getQuoteRequestFromRequestData(
        array $requestData,
        array $supportedAssets,
        string $accountId,
        ?string $accountMemo = null,
    ): Sep38QuoteRequest {
        // check sell asset
        $sellAssetInfo = self::getValidatedAsset(
            requestData: $requestData,
            supportedAssets: $supportedAssets,
            fieldKey: 'sell_asset',
        );

        // check buy asset
        $buyAssetInfo = self::getValidatedAsset(
            requestData: $requestData,
            supportedAssets: $supportedAssets,
            fieldKey: 'buy_asset',
        );

        // check sell amount
        $sellAmount = self::getValidatedAmount(requestData: $requestData, fieldKey: 'sell_amount');

        // check buy amount
        $buyAmount = self::getValidatedAmount(requestData: $requestData, fieldKey: 'buy_amount');

        if (($sellAmount !== null && $buyAmount !== null) || ($sellAmount === null && $buyAmount === null)) {
            throw new InvalidSepRequest('either sell_amount or buy_amount must be provided, but not both.');
        }

        $context = self::getValidatedContext(requestData: $requestData, allowedValues:['sep6', 'sep24', 'sep31']);

        $quoteRequest = new Sep38QuoteRequest(
            context: $context,
            sellAsset: $sellAssetInfo->asset,
            buyAsset: $buyAssetInfo->asset,
            accountId: $accountId,
            accountMemo: $accountMemo,
            sellAmount: $sellAmount,
            buyAmount: $buyAmount,
        );

        // check sell delivery method
        $quoteRequest->sellDeliveryMethod = self::getValidatedDeliveryMethod(
            requestData: $requestData,
            fieldKey: 'sell_delivery_method',
            supportedDeliveryMethods: $sellAssetInfo->sellDeliveryMethods,
        );

        // check buy delivery method
        $quoteRequest->buyDeliveryMethod = self::getValidatedDeliveryMethod(
            requestData: $requestData,
            fieldKey: 'buy_delivery_method',
            supportedDeliveryMethods: $buyAssetInfo->buyDeliveryMethods,
        );

        // check country code
        $quoteRequest->countryCode = self::getValidatedCountryCode(
            requestData: $requestData,
            sellAssetInfo: $sellAssetInfo,
            buyAssetInfo: $buyAssetInfo,
        );

        // check expire after
        $quoteRequest->expireAfter = self::getValidatedExpireAfter(requestData: $requestData);

        // check SEP31 send limits as soon as SEP31 is implemented

        return $quoteRequest;
    }

    /**
     * Extracts and validates the delivery method from the request data.
     *
     * @param array<array-key, mixed> $requestData the data to look for the delivery method.
     * @param string $fieldKey the key of the field within the request data.
     * @param array<Sep38DeliveryMethod>|null $supportedDeliveryMethods the supported delivery methods.
     *
     * @return string|null the delivery method if found and valid.
     *
     * @throws InvalidSepRequest if invalid or not supported.
     */
    private static function getValidatedDeliveryMethod(
        array $requestData,
        string $fieldKey,
        ?array $supportedDeliveryMethods,
    ): ?string {
        if (isset($requestData[$fieldKey])) {
            if (is_string($requestData[$fieldKey])) {
                $deliveryMethodStr = trim($requestData[$fieldKey]);
                if (!self::supportsDeliveryMethod($supportedDeliveryMethods, $deliveryMethodStr)) {
                    throw new InvalidSepRequest('Unsupported ' . $fieldKey);
                }
                if ($deliveryMethodStr !== '') {
                    return $deliveryMethodStr;
                }
            } else {
                throw new InvalidSepRequest($fieldKey . ' must be a string');
            }
        }

        return null;
    }

    /**
     * Checks if the given delivery method is supported.
     *
     * @param array<Sep38DeliveryMethod>|null $deliveryMethods available delivery methods
     * @param string|null $method method to check
     *
     * @return bool true if supported
     */
    private static function supportsDeliveryMethod(?array $deliveryMethods, ?string $method): bool
    {
        $noneIsAvailable = $deliveryMethods === null || count($deliveryMethods) === 0;
        $noneIsProvided = $method === null || trim($method) === '';
        if ($noneIsAvailable && $noneIsProvided) {
            return true;
        }
        if ($noneIsAvailable) {
            return false;
        }

        if ($noneIsProvided) {
            return true;
        }

        $result = false;
        if ($deliveryMethods !== null) {
            foreach ($deliveryMethods as $deliveryMethod) {
                if ($deliveryMethod->name === $method) {
                    $result = true;

                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Extracts and validates the given asset field from the request data. Checks if the asset in the right format
     * and if it is supported.
     *
     * @param array<array-key, mixed> $requestData the get transactions request data to validate and use.
     * @param array<Sep38AssetInfo> $supportedAssets array of supported assets.
     * @param string $fieldKey the key of the field within the requestData array.
     *
     * @return Sep38AssetInfo the corresponding, found asset from the given list of supported assets.
     *
     * @throws InvalidSepRequest if not valid, e.g. wrong format, not supported or not available.
     */
    private static function getValidatedAsset(
        array $requestData,
        array $supportedAssets,
        string $fieldKey,
    ): Sep38AssetInfo {
        $asset = null;
        if (isset($requestData[$fieldKey])) {
            if (is_string($requestData[$fieldKey])) {
                try {
                    $asset = IdentificationFormatAsset::fromString($requestData[$fieldKey]);
                } catch (InvalidAsset) {
                    throw new InvalidSepRequest($fieldKey . ' has an invalid format');
                }
            } else {
                throw new InvalidSepRequest($fieldKey . ' must be a string');
            }
        } else {
            throw new InvalidSepRequest($fieldKey . ' is required');
        }

        // check if asset is supported.
        $assetStr = $asset->getStringRepresentation();
        $assetInfo = null;
        foreach ($supportedAssets as $supportedAsset) {
            if ($supportedAsset->asset->getStringRepresentation() === $assetStr) {
                $assetInfo = $supportedAsset;

                break;
            }
        }
        if ($assetInfo === null) {
            throw new InvalidSepRequest($fieldKey . ' is not supported');
        }

        return $assetInfo;
    }

    /**
     * Extracts and validates an amount from the given request data array. Returns the validated amount if found.
     *
     * @param array<array-key, mixed> $requestData the request data to extract the amount from.
     * @param string $fieldKey the key of the amount field from the request data.
     *
     * @return string|null the amount value if found.
     *
     * @throws InvalidSepRequest if the amount given by the request data is invalid. E.g. not a float,
     * not greater than 0.
     */
    private static function getValidatedAmount(
        array $requestData,
        string $fieldKey,
    ): ?string {
        $amount = null;
        if (isset($requestData[$fieldKey])) {
            if (is_numeric($requestData[$fieldKey])) {
                $amount = floatval($requestData[$fieldKey]);
                if ($amount <= 0.0) {
                    throw new InvalidSepRequest($fieldKey . ' must be greater than zero');
                }
            } else {
                throw new InvalidSepRequest($fieldKey . ' must be a float');
            }
        }
        if ($amount !== null) {
            return strval($amount);
        }

        return null;
    }

    /**
     * Extracts and validates the country code from the request data.
     * Checks if the given asset supports the country code.
     *
     * @param array<array-key, mixed> $requestData the get transactions request data to get the country code from.
     * @param Sep38AssetInfo $sellAssetInfo the sell asset info to use for checking if the country code in the request
     * is supported for that asset.
     * @param Sep38AssetInfo | null $buyAssetInfo the buy asset info to use for checking if the country code in the request
     * is supported for that asset.
     *
     * @throws InvalidSepRequest if validation error occurs, such as unsupported country code.
     */
    private static function getValidatedCountryCode(
        array $requestData,
        Sep38AssetInfo $sellAssetInfo,
        ?Sep38AssetInfo $buyAssetInfo = null,
    ): ?string {
        if (isset($requestData['country_code'])) {
            if (is_string($requestData['country_code'])) {
                $countryCode = trim($requestData['country_code']);
                if ($sellAssetInfo->countryCodes === null || !in_array($countryCode, $sellAssetInfo->countryCodes)) {
                    throw new InvalidSepRequest('Unsupported country code');
                }

                if ($buyAssetInfo !== null) {
                    if ($buyAssetInfo->countryCodes === null || !in_array($countryCode, $buyAssetInfo->countryCodes)) {
                        throw new InvalidSepRequest('Unsupported country code');
                    }
                }

                return $countryCode;
            } else {
                throw new InvalidSepRequest('country_code must be a string');
            }
        }

        return null;
    }

    /**
     * Extracts and validates the context field value from the given request data.
     *
     * @param array<array-key, mixed> $requestData the request data to extract and validate the 'context' field value from.
     * @param array<string> $allowedValues list of allowed values as strings such as 'sep6', 'sep24', 'sep31'
     *
     * @return string the validated context value.
     *
     * @throws InvalidSepRequest if invalid or not found.
     */
    private static function getValidatedContext(array $requestData, array $allowedValues): string
    {
        $context = null;
        if (isset($requestData['context'])) {
            if (is_string($requestData['context'])) {
                $context = $requestData['context'];
            } else {
                throw new InvalidSepRequest('context must be a string');
            }
        } else {
            throw new InvalidSepRequest('context is required');
        }

        if (!in_array($context, $allowedValues)) {
            throw new InvalidSepRequest('context must be one of ' . implode(', ', $allowedValues));
        }

        return $context;
    }

    /**
     * Extracts and validates the 'expire_after' value from the request data if any.
     *
     * @param array<array-key, mixed> $requestData the request data to extract and validate the 'expire_after' field value from.
     *
     * @return DateTime|null the found and validated expire_after value
     *
     * @throws InvalidSepRequest if found but invalid.
     */
    private static function getValidatedExpireAfter(array $requestData): ?DateTime
    {
        if (isset($requestData['expire_after'])) {
            if (is_string($requestData['expire_after'])) {
                $expireAfterStr = $requestData['expire_after'];
                $dateTime = DateTime::createFromFormat(DATE_ATOM, $expireAfterStr);
                if ($dateTime === false) {
                    throw new InvalidSepRequest('expire_after is not a valid ISO 8601 date');
                }

                return $dateTime;
            } else {
                throw new InvalidSepRequest('expire_after must be a string');
            }
        }

        return null;
    }
}
