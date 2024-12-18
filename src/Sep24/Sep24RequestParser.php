<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep24;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
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

use function array_key_exists;
use function array_keys;
use function count;
use function floatval;
use function is_int;
use function is_numeric;
use function is_string;
use function json_encode;
use function trim;

use const DATE_ATOM;

class Sep24RequestParser
{
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

    /**
     * Extracts the asset data from the request data, validates it and creates an IdentificationFormatAsset
     * from the extracted data.
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return IdentificationFormatAsset the asset
     *
     * @throws InvalidSepRequest if the request data is invalid. e.g. missing asset code.
     */
    public static function getAssetFromRequestData(array $requestData): IdentificationFormatAsset
    {
        $assetCode = null;
        if (isset($requestData['asset_code'])) {
            if (is_string($requestData['asset_code'])) {
                $assetCode = $requestData['asset_code'];
            } else {
                throw new InvalidSepRequest(
                    message: 'asset code must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'asset code'],
                );
            }
        } else {
            throw new InvalidSepRequest(
                message: 'missing asset code',
                messageKey: 'shared_lang.error.request.field_required',
                messageParams: ['field' => 'asset code'],
            );
        }

        $assetIssuer = null;
        if (isset($requestData['asset_issuer'])) {
            if (is_string($requestData['asset_issuer'])) {
                $assetIssuer = $requestData['asset_issuer'];
                try {
                    KeyPair::fromAccountId($assetIssuer);
                } catch (Throwable $th) {
                    self::getLogger()->debug(
                        'Invalid asset issuer, must be a valid account id',
                        ['context' => 'sep24', 'error' => $th->getMessage(), 'exception' => $th],
                    );

                    throw new InvalidSepRequest(
                        message: 'invalid asset issuer, must be a valid account id',
                        messageKey: 'shared_lang.error.request.invalid_asset_issuer',
                    );
                }
            } else {
                throw new InvalidSepRequest(
                    message: 'asset issuer must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'asset issuer'],
                );
            }
        }

        if ($assetCode === IdentificationFormatAsset::NATIVE_ASSET_CODE && $assetIssuer !== null) {
            throw new InvalidSepRequest(
                message: 'invalid asset issuer ' . $assetIssuer . " for asset code 'native'",
                messageKey: 'asset_lang.error.request.invalid_asset_issuer_native',
                messageParams: ['issuer' => $assetIssuer],
            );
        }

        try {
            return new IdentificationFormatAsset(
                IdentificationFormatAsset::ASSET_SCHEMA_STELLAR,
                $assetCode,
                $assetIssuer,
            );
        } catch (InvalidAsset $invalidAsset) {
            self::getLogger()->debug(
                'Invalid asset.',
                ['context' => 'sep24', 'error' => $invalidAsset->getMessage(), 'exception' => $invalidAsset],
            );

            throw new InvalidSepRequest(
                message: 'invalid asset: ' . $invalidAsset->getMessage(),
                messageKey: 'shared_lang.error.invalid_asset',
            );
        }
    }

    /**
     * Extracts the destination asset data from the request data, validates it and creates an IdentificationFormatAsset
     * from the extracted data.
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return IdentificationFormatAsset|null the destination asset if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getDestinationAssetFromRequestData(array $requestData): ?IdentificationFormatAsset
    {
        $destinationAsset = null;
        if (isset($requestData['destination_asset'])) {
            if (is_string($requestData['destination_asset'])) {
                $destinationAssetStr = $requestData['destination_asset'];
                try {
                    $destinationAsset = IdentificationFormatAsset::fromString($destinationAssetStr);
                } catch (InvalidAsset $invalidAsset) {
                    self::getLogger()->debug(
                        'Invalid destination asset.',
                        ['context' => 'sep24', 'error' => $invalidAsset->getMessage(), 'exception' => $invalidAsset],
                    );

                    throw new InvalidSepRequest(
                        message: 'invalid destination asset: ' . $invalidAsset->getMessage(),
                        messageKey: 'sep06_lang.error.request.invalid_destination_asset',
                    );
                }
            } else {
                throw new InvalidSepRequest(
                    message: 'destination asset must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'destination asset'],
                );
            }
        }

        return $destinationAsset;
    }

    /**
     * Extracts the source asset data from the request data if available,
     * validates it and creates an IdentificationFormatAsset from the extracted data.
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return IdentificationFormatAsset|null the source asset if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getSourceAssetFromRequestData(array $requestData): ?IdentificationFormatAsset
    {
        $sourceAsset = null;
        if (isset($requestData['source_asset'])) {
            if (is_string($requestData['source_asset'])) {
                $sourceAssetStr = $requestData['source_asset'];
                try {
                    $sourceAsset = IdentificationFormatAsset::fromString($sourceAssetStr);
                } catch (InvalidAsset $invalidAsset) {
                    self::getLogger()->debug(
                        'Invalid source asset.',
                        ['context' => 'sep24', 'error' => $invalidAsset->getMessage(), 'exception' => $invalidAsset],
                    );

                    throw new InvalidSepRequest(
                        message: 'invalid source asset: ' . $invalidAsset->getMessage(),
                        messageKey: 'sep06_lang.error.request.invalid_source_asset',
                    );
                }
            } else {
                throw new InvalidSepRequest(
                    message: 'source asset must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'source asset'],
                );
            }
        }

        return $sourceAsset;
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
            self::getLogger()->debug(
                'Validating amount.',
                ['context' => 'sep24', 'value' => $requestData['amount']],
            );

            if (is_numeric($requestData['amount'])) {
                $amount = floatval($requestData['amount']);
            } else {
                throw new InvalidSepRequest(
                    message: 'amount must be a float',
                    messageKey: 'shared_lang.error.request.field_must_be_a_float',
                    messageParams: ['field' => 'amount'],
                );
            }
        }

        return $amount;
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
        $quoteId = null;
        if (isset($requestData['quote_id'])) {
            self::getLogger()->debug(
                'Validating quote id.',
                ['context' => 'sep24', 'value' => $requestData['quote_id']],
            );

            if (is_string($requestData['quote_id'])) {
                $quoteId = $requestData['quote_id'];
            } else {
                throw new InvalidSepRequest(
                    message: 'quote id must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'quote id'],
                );
            }
        }

        return $quoteId;
    }

    /**
     * Extracts the wallet name from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string|null the wallet name if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getWalletNameFromRequestData(array $requestData): ?string
    {
        $result = null;
        if (isset($requestData['wallet_name'])) {
            self::getLogger()->debug(
                'Validating wallet name.',
                ['context' => 'sep24', 'value' => $requestData['wallet_name']],
            );

            if (is_string($requestData['wallet_name'])) {
                $result = $requestData['wallet_name'];
            } else {
                throw new InvalidSepRequest(
                    message: 'wallet_name must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'wallet name'],
                );
            }
        }

        return $result;
    }

    /**
     * Extracts the wallet url from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return string|null the wallet url if found.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function getWalletUrlFromRequestData(array $requestData): ?string
    {
        $result = null;
        if (isset($requestData['wallet_url'])) {
            self::getLogger()->debug(
                'Validating wallet url.',
                ['context' => 'sep24', 'value' => $requestData['wallet_url']],
            );

            if (is_string($requestData['wallet_url'])) {
                $result = $requestData['wallet_url'];
            } else {
                throw new InvalidSepRequest(
                    message: 'wallet_url must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'wallet url'],
                );
            }
        }

        return $result;
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
        $result = null;
        if (isset($requestData['lang'])) {
            self::getLogger()->debug(
                'Validating lang.',
                ['context' => 'sep24', 'value' => $requestData['lang']],
            );

            if (is_string($requestData['lang'])) {
                $result = $requestData['lang'];
            } else {
                throw new InvalidSepRequest(
                    message: 'lang must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'lang'],
                );
            }
        }

        return $result;
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
        $customerId = null;
        if (isset($requestData['customer_id'])) {
            self::getLogger()->debug(
                'Validating customer id.',
                ['context' => 'sep24', 'value' => $requestData['customer_id']],
            );

            if (is_string($requestData['customer_id'])) {
                $customerId = $requestData['customer_id'];
            } else {
                throw new InvalidSepRequest(
                    message: 'customer id must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'customer id'],
                );
            }
        }

        return $customerId;
    }

    /**
     * Extracts the account id from the request data
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     * @param Sep10Jwt $token the SEP-10 jwt token.
     *
     * @return string the account id.
     *
     * @throws InvalidSepRequest if the request data is invalid. E.g. not a stellar account id.
     */
    public static function getAccountFromRequestData(array $requestData, Sep10Jwt $token): string
    {
        $account = null;
        if (isset($requestData['account'])) {
            if (is_string($requestData['account'])) {
                $account = $requestData['account'];
            } else {
                throw new InvalidSepRequest(
                    message: 'account must be a string',
                    messageKey: 'shared_lang.error.request.field_must_be_string',
                    messageParams: ['field' => 'account'],
                );
            }
        } else {
            $account = $token->accountId ?? $token->muxedAccountId;
        }

        if ($account === null) {
            throw new InvalidSepRequest('could not find account');
        } else {
            try {
                KeyPair::fromAccountId($account);
            } catch (Throwable $th) {
                self::getLogger()->debug(
                    'Invalid account, must be a valid account id.',
                    ['context' => 'sep24', 'error' => $th->getMessage(), 'exception' => $th],
                );

                throw new InvalidSepRequest(
                    message: 'invalid account, must be a valid account id',
                    messageKey: 'sep24_lang.error.account_not_found',
                );
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
        $memoStr = null;
        if (isset($requestData['memo'])) {
            if (is_string($requestData['memo'])) {
                $memoStr = $requestData['memo'];
            } else {
                throw new InvalidSepRequest(
                    message: 'refund memo must be a string',
                    messageKey: 'shared_lang.error.request.refund_memo.must_be_string',
                );
            }
        }

        $memoTypeStr = null;
        if (isset($requestData['memo_type'])) {
            if (is_string($requestData['memo_type'])) {
                $memoTypeStr = trim($requestData['memo_type']);
                if ($memoTypeStr !== 'id') {
                    throw new InvalidSepRequest('memo type ' . $memoTypeStr .
                        ' not supported. only memo type id is supported');
                }
            } else {
                throw new InvalidSepRequest('memo type must be a string');
            }
        }

        $memo = null;
        if ($memoStr !== null) {
            if ($memoTypeStr === null) {
                $memoTypeStr = 'id';
            }
            $memo = MemoHelper::makeMemoFromSepRequestData($memoStr, $memoTypeStr);
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
     * @throws InvalidSepRequest if the request data is invalid
     */
    public static function getRefundMemoFromRequestData(array $requestData): ?Memo
    {
        $refundMemo = null;
        $refundMemoStr = null;
        if (isset($requestData['refund_memo'])) {
            if (is_string($requestData['refund_memo'])) {
                $refundMemoStr = $requestData['refund_memo'];
            } else {
                throw new InvalidSepRequest('refund memo must be a string');
            }
        }

        $refundMemoTypeStr = null;
        if (isset($requestData['refund_memo_type'])) {
            if (is_string($requestData['refund_memo_type'])) {
                $refundMemoTypeStr = $requestData['refund_memo_type'];
                if ($refundMemoStr === null) {
                    throw new InvalidSepRequest('refund memo type is specified but refund memo missing');
                }
                $refundMemo = MemoHelper::makeMemoFromSepRequestData($refundMemoStr, $refundMemoTypeStr);
            } else {
                throw new InvalidSepRequest('refund memo type must be a string');
            }
        }

        if ($refundMemoStr !== null && $refundMemoTypeStr === null) {
            throw new InvalidSepRequest('missing refund memo type');
        }

        return $refundMemo;
    }

    /**
     * Extracts the kyc fields from the request data if available
     *
     * @param array<array-key, mixed> $requestData parsed request data.
     *
     * @return array<array-key, mixed>|null the found kyc fields if any.
     */
    public static function getKycFieldsFromRequestData(array $requestData): ?array
    {
        /**
         * @var array<array-key, mixed> $result
         */
        $result = $requestData;
        $keysToExclude = ['asset_code', 'asset_issuer', 'source_asset', 'destination_asset',
            'amount', 'quote_id', 'account', 'memo', 'memo_type', 'refund_memo', 'refund_memo_type',
            'wallet_name', 'wallet_url', 'lang', 'claimable_balance_supported', 'customer_id',
        ];

        foreach (array_keys($result) as $key) {
            if (array_key_exists($key, $keysToExclude)) {
                unset($result[$key]);
            }
        }

        self::getLogger()->debug(
            'Retrieving kyc fields from request data.',
            ['context' => 'sep24', 'keys_to_be_excluded' => json_encode($keysToExclude),
                'result' => json_encode($result),
            ],
        );

        if (count($result) === 0) {
            return null;
        }

        return $result;
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
                throw new InvalidSepRequest('asset_code must be a string');
            }
        } else {
            throw new InvalidSepRequest('asset_code is required');
        }

        $noOlderThan = null;
        if (isset($requestData['no_older_than'])) {
            self::getLogger()->debug(
                'Validating the no older than field.',
                ['context' => 'sep24', 'value' => $requestData['no_older_than']],
            );

            if (is_string($requestData['no_older_than'])) {
                $noOlderThanStr = $requestData['no_older_than'];
                $dateTime = DateTime::createFromFormat(DATE_ATOM, $noOlderThanStr);
                if ($dateTime === false) {
                    throw new InvalidSepRequest('no_older_than is not a valid ISO 8601 date');
                }
                $noOlderThan = $dateTime;
            } else {
                throw new InvalidSepRequest('no_older_than must be a UTC ISO 8601 string');
            }
        }

        $limit = null;
        if (isset($requestData['limit'])) {
            self::getLogger()->debug(
                'Validating the limit field.',
                ['context' => 'sep24', 'value' => $requestData['limit']],
            );
            if (is_int($requestData['limit'])) {
                $limit = $requestData['limit'];
            } else {
                throw new InvalidSepRequest('limit must be an integer');
            }
        }

        $kind = null;
        if (isset($requestData['kind'])) {
            if (is_string($requestData['kind'])) {
                $kind = $requestData['kind'];
                self::getLogger()->debug(
                    'Validating the kind field.',
                    ['context' => 'sep24', 'value' => $requestData['kind']],
                );

                if ($kind !== 'deposit' && $kind !== 'withdrawal') {
                    throw new InvalidSepRequest('kind must be either deposit or withdrawal.');
                }
            } else {
                throw new InvalidSepRequest('kind must be a string');
            }
        }

        $pagingId = null;
        if (isset($requestData['paging_id'])) {
            self::getLogger()->debug(
                'Validating the paging id.',
                ['context' => 'sep24', 'value' => $requestData['paging_id']],
            );

            if (is_string($requestData['paging_id'])) {
                $pagingId = $requestData['paging_id'];
            } else {
                throw new InvalidSepRequest('paging_id must be a string');
            }
        }

        $lang = null;
        if (isset($requestData['lang'])) {
            if (is_string($requestData['lang'])) {
                $lang = $requestData['lang'];
            } else {
                throw new InvalidSepRequest('lang must be a string');
            }
        }

        return new TransactionHistoryRequest($assetCode, $noOlderThan, $limit, $kind, $pagingId, $lang);
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
