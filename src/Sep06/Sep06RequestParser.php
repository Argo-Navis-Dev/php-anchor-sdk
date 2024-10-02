<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep06;

use ArgoNavis\PhpAnchorSdk\callback\TransactionHistoryRequest;
use ArgoNavis\PhpAnchorSdk\exception\InvalidAsset;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\util\MemoHelper;
use DateTime;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Memo;
use Throwable;

use function floatval;
use function in_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_encode;
use function trim;

use const DATE_ATOM;

class Sep06RequestParser
{
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

    /**
     * Extracts the asset code from the request data
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string the asset code
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. asset code not found.
     */
    public static function getAssetCodeFromRequestData(array $requestData): string
    {
        try {
            return self::getStringValueFromRequestData('asset_code', $requestData) ??
                throw new InvalidSepRequest('missing asset_code');
        } catch (InvalidSepRequest $e) {
            self::getLogger()->error(
                'Asset code not found in request data.',
                ['context' => 'sep06'],
            );

            throw $e;
        }
    }

    /**
     * Extracts the destination asset code from the request data
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string the asset code
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. destination asset code not found.
     */
    public static function getDestinationAssetCodeFromRequestData(array $requestData): string
    {
        try {
            return self::getStringValueFromRequestData('destination_asset', $requestData) ??
                throw new InvalidSepRequest('missing destination_asset');
        } catch (InvalidSepRequest $e) {
            self::getLogger()->error(
                'Destination asset not found in request data.',
                ['context' => 'sep06'],
            );

            throw $e;
        }
    }

    /**
     * Extracts the source asset code from the request data
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string the asset code
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. source asset code not found.
     */
    public static function getSourceAssetCodeFromRequestData(array $requestData): string
    {
        try {
            return self::getStringValueFromRequestData('source_asset', $requestData) ??
                throw new InvalidSepRequest('missing source_asset');
        } catch (InvalidSepRequest $e) {
            self::getLogger()->error(
                'Source asset not found in request data.',
                ['context' => 'sep06'],
            );

            throw $e;
        }
    }

    /**
     * Extracts the source asset from the deposit exchange request data
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return IdentificationFormatAsset the asset
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. source asset not found or not in sep-38 format.
     */
    public static function getSourceAssetFromRequestData(array $requestData): IdentificationFormatAsset
    {
        $sourceAssetStr = self::getStringValueFromRequestData('source_asset', $requestData) ??
            throw new InvalidSepRequest('missing source_asset');

        try {
            return IdentificationFormatAsset::fromString($sourceAssetStr);
        } catch (InvalidAsset $invalidAsset) {
            self::getLogger()->error(
                'Invalid source asset.',
                ['context' => 'sep06', 'source_asset' => $sourceAssetStr],
            );

            throw new InvalidSepRequest('invalid source_asset: ' . $invalidAsset->getMessage());
        }
    }

    /**
     * Extracts the destination asset code from the withdraw exchange request data
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return IdentificationFormatAsset the asset
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. destination asset not found or not in sep-38 format.
     */
    public static function getDestinationAssetFromRequestData(array $requestData): IdentificationFormatAsset
    {
        $destAssetStr = self::getStringValueFromRequestData('destination_asset', $requestData) ??
            throw new InvalidSepRequest('missing destination_asset');

        try {
            return IdentificationFormatAsset::fromString($destAssetStr);
        } catch (InvalidAsset $invalidAsset) {
            self::getLogger()->error(
                'Invalid destination asset.',
                ['context' => 'sep06', 'destination_asset' => $destAssetStr],
            );

            throw new InvalidSepRequest('invalid destination_asset: ' . $invalidAsset->getMessage());
        }
    }

    /**
     * Extracts the account id from the request data
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string the account id.
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. not a stellar account id.
     */
    public static function getAccountFromRequestData(array $requestData): string
    {
        $account = self::getStringValueFromRequestData('account', $requestData);

        if ($account === null) {
            self::getLogger()->error(
                'Account not found in request data.',
                ['context' => 'sep06'],
            );

            throw new InvalidSepRequest('missing account');
        } else {
            try {
                KeyPair::fromAccountId($account);
            } catch (Throwable $e) {
                self::getLogger()->error(
                    'Invalid account.',
                    ['context' => 'sep06', 'error' => $e->getMessage(), 'exception' => $e],
                );

                throw new InvalidSepRequest('invalid account, must be a valid account id');
            }
        }

        return $account;
    }

    /**
     * Extracts the account id from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return ?string the account id.
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. not a stellar account id.
     */
    public static function getAccountOptionalFromRequestData(array $requestData): ?string
    {
        $account = self::getStringValueFromRequestData('account', $requestData);

        if ($account === null) {
            self::getLogger()->debug(
                'Account (optional) not found in request data.',
                ['context' => 'sep06'],
            );

            return null;
        } else {
            try {
                KeyPair::fromAccountId($account);
            } catch (Throwable $e) {
                self::getLogger()->error(
                    'Invalid account.',
                    ['context' => 'sep06', 'error' => $e->getMessage(), 'exception' => $e],
                );

                throw new InvalidSepRequest('invalid account, must be a valid account id');
            }
        }

        return $account;
    }

    /**
     * Extracts the memo from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return Memo|null the memo if found.
     *
     * @throws InvalidSepRequest if the request data is invalid or not supported
     */
    public static function getMemoFromRequestData(array $requestData): ?Memo
    {
        $memoStr = self::getStringValueFromRequestData('memo', $requestData);
        self::getLogger()->debug(
            'Retrieving memo from request data.',
            ['context' => 'sep06', 'memo' => $memoStr],
        );

        $memoTypeStr = null;
        if (isset($requestData['memo_type'])) {
            if (is_string($requestData['memo_type'])) {
                $memoTypeStr = trim($requestData['memo_type']);
                if (!in_array($memoTypeStr, ['id', 'text', 'hash'])) {
                    self::getLogger()->error(
                        'Memo type not supported.',
                        ['context' => 'sep06', 'memo_type' => $memoTypeStr],
                    );

                    throw new InvalidSepRequest('memo type ' . $memoTypeStr . ' not supported.');
                }
            } else {
                self::getLogger()->error(
                    'Memo type must be a string.',
                    ['context' => 'sep06'],
                );

                throw new InvalidSepRequest('memo type must be a string');
            }
        }

        $memo = null;
        if ($memoStr !== null) {
            if ($memoTypeStr === null) {
                self::getLogger()->error(
                    'Memo type must be provided if memo is provided.',
                    ['context' => 'sep06'],
                );

                throw new InvalidSepRequest('memo type must be provided if memo is provided');
            }
            $memo = MemoHelper::makeMemoFromSepRequestData($memoStr, $memoTypeStr);
            if ($memo !== null) {
                self::getLogger()->debug(
                    'The parsed memo.',
                    ['context' => 'sep06', 'memo' => $memo->valueAsString()],
                );
            }
        }

        return $memo;
    }

    /**
     * Extracts the refund memo from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return Memo|null the refund memo if found.
     *
     * @throws InvalidSepRequest if the request data is invalid or not supported
     */
    public static function getRefundMemoFromRequestData(array $requestData): ?Memo
    {
        $memoStr = self::getStringValueFromRequestData('refund_memo', $requestData);

        $memoTypeStr = null;
        if (isset($requestData['refund_memo_type'])) {
            if (is_string($requestData['refund_memo_type'])) {
                $memoTypeStr = trim($requestData['refund_memo_type']);
                if (!in_array($memoTypeStr, ['id', 'text', 'hash'])) {
                    self::getLogger()->error(
                        'Refund memo type not supported.',
                        ['context' => 'sep06', 'refund_memo_type' => $memoTypeStr],
                    );

                    throw new InvalidSepRequest('refund_memo_type ' . $memoTypeStr . ' not supported.');
                }
            } else {
                self::getLogger()->error(
                    'Refund memo type must be a string.',
                    ['context' => 'sep06'],
                );

                throw new InvalidSepRequest('refund_memo_type must be a string');
            }
        }

        $memo = null;
        if ($memoStr !== null) {
            if ($memoTypeStr === null) {
                self::getLogger()->error(
                    'Refund memo type must be provided if refund memo is provided.',
                    ['context' => 'sep06'],
                );

                throw new InvalidSepRequest('refund_memo_type must be provided if refund_memo is provided');
            }
            $memo = MemoHelper::makeMemoFromSepRequestData($memoStr, $memoTypeStr);
            if ($memo !== null) {
                self::getLogger()->debug(
                    'The parsed refund memo.',
                    ['context' => 'sep06', 'memo' => $memo->valueAsString()],
                );
            }
        }

        return $memo;
    }

    /**
     * Extracts the email address from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return ?string the email address if found
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. email address is not a string
     */
    public static function getEmailAddressFromRequestData(array $requestData): ?string
    {
        return self::getStringValueFromRequestData('email_address', $requestData);
    }

    /**
     * Extracts the type (of deposit/withdrawal) from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return ?string the types if found
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. type is not a string
     */
    public static function getTypeFromRequestData(array $requestData): ?string
    {
        return self::getStringValueFromRequestData('type', $requestData);
    }

    /**
     * Extracts the language from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string|null the language if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getLangFromRequestData(array $requestData): ?string
    {
        return self::getStringValueFromRequestData('lang', $requestData);
    }

    /**
     * Extracts the quote id from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string|null the quote id if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getQuoteIdFromRequestData(array $requestData): ?string
    {
        return self::getStringValueFromRequestData('quote_id', $requestData);
    }

    /**
     * Extracts the on change callback url from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string|null the on change callback url if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getOnChangeCallbackUrlFromRequestData(array $requestData): ?string
    {
        return self::getStringValueFromRequestData('on_change_callback', $requestData);
    }

    /**
     * Extracts the amount from the request data.
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return float|null the amount if found.
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. amount is not a float.
     */
    public static function getAmountFromRequestData(array $requestData): ?float
    {
        $amount = null;
        if (isset($requestData['amount'])) {
            if (is_numeric($requestData['amount'])) {
                $amount = floatval($requestData['amount']);
            } else {
                self::getLogger()->error(
                    'Amount must be a float.',
                    ['context' => 'sep06'],
                );

                throw new InvalidSepRequest('amount must be a float');
            }
        }

        return $amount;
    }

    /**
     * Extracts the country code from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string|null the country code if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getCountryCodeFromRequestData(array $requestData): ?string
    {
        return self::getStringValueFromRequestData('country_code', $requestData);
    }

    /**
     * Extracts the claimable balance supported flag from the request data.
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return bool true if claimable balances are supported by the client.
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. flag is not a boolean.
     */
    public static function getClaimableBalanceSupportedRequestData(array $requestData): bool
    {
        $supported = false;
        if (
            isset($requestData['claimable_balance_supported']) &&
            $requestData['claimable_balance_supported'] === 'true'
        ) {
            $supported = true;
        }

        return $supported;
    }

    /**
     * Extracts the customer id from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string|null the customer id if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getCustomerIdFromRequestData(array $requestData): ?string
    {
        return self::getStringValueFromRequestData('customer_id', $requestData);
    }

    /**
     * Extracts the location id from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string|null the location id if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getLocationIdFromRequestData(array $requestData): ?string
    {
        return self::getStringValueFromRequestData('location_id', $requestData);
    }

    /**
     * Extracts a string value from the request data for the given field name id available.
     *
     * @param string $fieldName the name of the field within the request data to parse the value for.
     * @param array<array-key, mixed> $requestData the request data to parse the value from.
     *
     * @return string|null the result if available.
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. the field value is not a string.
     */
    public static function getStringValueFromRequestData(string $fieldName, array $requestData): ?string
    {
        if (isset($requestData[$fieldName])) {
            if (is_string($requestData[$fieldName])) {
                return $requestData[$fieldName];
            } else {
                self::getLogger()->error(
                    'The field must be a string.',
                    ['context' => 'sep06', 'field_name' => $fieldName],
                );

                throw new InvalidSepRequest($fieldName . ' must be a string');
            }
        }

        return null;
    }

    /**
     * Validates the given request data and creates a Sep24TransactionHistoryRequest from it.
     *
     * @param array<array-key, mixed> $requestData the get transactions request data to validate and use.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getTransactionsRequestFromRequestData(array $requestData): TransactionHistoryRequest
    {
        $assetCode = null;
        if (isset($requestData['asset_code'])) {
            if (is_string($requestData['asset_code'])) {
                $assetCode = $requestData['asset_code'];
            } else {
                self::getLogger()->error(
                    'Asset code must be a string.',
                    ['context' => 'sep06', 'asset_code' => $assetCode],
                );

                throw new InvalidSepRequest('asset_code must be a string');
            }
        } else {
            self::getLogger()->error(
                'Asset code is required.',
                ['context' => 'sep06'],
            );

            throw new InvalidSepRequest('asset_code is required');
        }

        $noOlderThan = null;
        if (isset($requestData['no_older_than'])) {
            if (is_string($requestData['no_older_than'])) {
                $noOlderThanStr = $requestData['no_older_than'];
                $dateTime = DateTime::createFromFormat(DATE_ATOM, $noOlderThanStr);
                if ($dateTime === false) {
                    $error = 'no_older_than is not a valid ISO 8601 date';
                    self::getLogger()->error($error, ['context' => 'sep06', 'no_older_than_str' => $noOlderThanStr]);

                    throw new InvalidSepRequest($error);
                }
                self::getLogger()->debug(
                    'The no_older_than value',
                    ['context' => 'sep06', 'no_older_than_str' => $dateTime->format('Y-m-d H:i:s')],
                );
                $noOlderThan = $dateTime;
            } else {
                $error = 'no_older_than must be a UTC ISO 8601 string';
                self::getLogger()->error($error, ['context' => 'sep06']);

                throw new InvalidSepRequest($error);
            }
        }

        $limit = null;
        if (isset($requestData['limit'])) {
            if (is_int($requestData['limit'])) {
                $limit = $requestData['limit'];
            } else {
                self::getLogger()->error('Limit must be an integer.', ['context' => 'sep06']);

                throw new InvalidSepRequest('limit must be an integer');
            }
        }

        $kind = null;
        if (isset($requestData['kind'])) {
            if (is_string($requestData['kind'])) {
                $kind = $requestData['kind'];
                if ($kind !== 'deposit' && $kind !== 'withdrawal') {
                    $error = 'kind must be either deposit or withdrawal.';
                    self::getLogger()->error($error, ['context' => 'sep06', 'kind' => $kind]);

                    throw new InvalidSepRequest($error);
                }
            } else {
                $error = 'kind must be a string';
                self::getLogger()->error($error, ['context' => 'sep06']);

                throw new InvalidSepRequest($error);
            }
        }

        $pagingId = null;
        if (isset($requestData['paging_id'])) {
            if (is_string($requestData['paging_id'])) {
                $pagingId = $requestData['paging_id'];
            } else {
                $error = 'paging_id must be a string';
                self::getLogger()->error($error, ['context' => 'sep06']);

                throw new InvalidSepRequest($error);
            }
        }

        $lang = null;
        if (isset($requestData['lang'])) {
            if (is_string($requestData['lang'])) {
                $lang = $requestData['lang'];
            } else {
                $error = 'lang must be a string';
                self::getLogger()->error($error, ['context' => 'sep06']);

                throw new InvalidSepRequest($error);
            }
        }
        $transactionHistoryRequest = new TransactionHistoryRequest(
            $assetCode,
            $noOlderThan,
            $limit,
            $kind,
            $pagingId,
            $lang,
        );
        self::getLogger()->error(
            'The transaction history request data.',
            ['context' => 'sep06', 'data' => json_encode($transactionHistoryRequest)],
        );

        return $transactionHistoryRequest;
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
