<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep24;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\callback\Sep24TransactionHistoryRequest;
use ArgoNavis\PhpAnchorSdk\exception\InvalidAsset;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\util\MemoHelper;
use DateTime;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Memo;
use Throwable;

use function array_key_exists;
use function array_keys;
use function count;
use function floatval;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;
use function trim;

use const DATE_ATOM;

class Sep24RequestParser
{
    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getAssetFromRequestData(array $requestData): IdentificationFormatAsset
    {
        $assetCode = null;
        if (isset($requestData['asset_code'])) {
            if (is_string($requestData['asset_code'])) {
                $assetCode = $requestData['asset_code'];
            } else {
                throw new InvalidSepRequest('asset code must be a string');
            }
        } else {
            throw new InvalidSepRequest('missing asset code');
        }

        $assetIssuer = null;
        if (isset($requestData['asset_issuer'])) {
            if (is_string($requestData['asset_issuer'])) {
                $assetIssuer = $requestData['asset_issuer'];
                try {
                    KeyPair::fromAccountId($assetIssuer);
                } catch (Throwable) {
                    throw new InvalidSepRequest('invalid asset issuer, must be a valid account id');
                }
            } else {
                throw new InvalidSepRequest('asset issuer must be a string');
            }
        }

        if ($assetCode === IdentificationFormatAsset::NATIVE_ASSET_CODE && $assetIssuer !== null) {
            throw new InvalidSepRequest('invalid asset issuer ' . $assetIssuer . " for asset code 'native'");
        }

        try {
            return new IdentificationFormatAsset(
                IdentificationFormatAsset::ASSET_SCHEMA_STELLAR,
                $assetCode,
                $assetIssuer,
            );
        } catch (InvalidAsset $invalidAsset) {
            throw new InvalidSepRequest('invalid asset: ' . $invalidAsset->getMessage());
        }
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
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
                    throw new InvalidSepRequest('invalid destination asset: ' . $invalidAsset->getMessage());
                }
            } else {
                throw new InvalidSepRequest('destination asset must be a string');
            }
        }

        return $destinationAsset;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
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
                    throw new InvalidSepRequest('invalid source asset: ' . $invalidAsset->getMessage());
                }
            } else {
                throw new InvalidSepRequest('source asset must be a string');
            }
        }

        return $sourceAsset;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getAmountFromRequestData(array $requestData): ?float
    {
        $amount = null;
        if (isset($requestData['amount'])) {
            if (is_numeric($requestData['amount'])) {
                $amount = floatval($requestData['amount']);
            } else {
                throw new InvalidSepRequest('amount must be a float');
            }
        }

        return $amount;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getClaimableBalanceSupportedRequestData(array $requestData): bool
    {
        $supported = false;
        if (isset($requestData['claimable_balance_supported'])) {
            if (is_bool($requestData['claimable_balance_supported'])) {
                $supported = $requestData['claimable_balance_supported'];
            } else {
                throw new InvalidSepRequest('claimable_balance_supported must be a boolean');
            }
        }

        return $supported;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getQuoteIdFromRequestData(array $requestData): ?string
    {
        $quoteId = null;
        if (isset($requestData['quote_id'])) {
            if (is_string($requestData['quote_id'])) {
                $quoteId = $requestData['quote_id'];
            } else {
                throw new InvalidSepRequest('quote id must be a string');
            }
        }

        return $quoteId;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getWalletNameFromRequestData(array $requestData): ?string
    {
        $result = null;
        if (isset($requestData['wallet_name'])) {
            if (is_string($requestData['wallet_name'])) {
                $result = $requestData['wallet_name'];
            } else {
                throw new InvalidSepRequest('wallet_name must be a string');
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getWalletUrlFromRequestData(array $requestData): ?string
    {
        $result = null;
        if (isset($requestData['wallet_url'])) {
            if (is_string($requestData['wallet_url'])) {
                $result = $requestData['wallet_url'];
            } else {
                throw new InvalidSepRequest('wallet_url must be a string');
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getLangFromRequestData(array $requestData): ?string
    {
        $result = null;
        if (isset($requestData['lang'])) {
            if (is_string($requestData['lang'])) {
                $result = $requestData['lang'];
            } else {
                throw new InvalidSepRequest('lang must be a string');
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getCustomerIdFromRequestData(array $requestData): ?string
    {
        $customerId = null;
        if (isset($requestData['customer_id'])) {
            if (is_string($requestData['customer_id'])) {
                $customerId = $requestData['customer_id'];
            } else {
                throw new InvalidSepRequest('customer id must be a string');
            }
        }

        return $customerId;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getAccountFromRequestData(array $requestData, Sep10Jwt $token): string
    {
        $account = null;
        if (isset($requestData['account'])) {
            if (is_string($requestData['account'])) {
                $account = $requestData['account'];
            } else {
                throw new InvalidSepRequest('account must be a string');
            }
        } else {
            $account = $token->accountId ?? $token->muxedAccountId;
        }

        if ($account === null) {
            throw new InvalidSepRequest('could not find account');
        } else {
            try {
                KeyPair::fromAccountId($account);
            } catch (Throwable) {
                throw new InvalidSepRequest('invalid account, must be a valid account id');
            }
        }

        return $account;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getMemoFromRequestData(array $requestData): ?Memo
    {
        $memoStr = null;
        if (isset($requestData['memo'])) {
            if (is_string($requestData['memo'])) {
                $memoStr = $requestData['memo'];
            } else {
                throw new InvalidSepRequest('memo must be a string');
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
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
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
     * @param array<array-key, mixed> $requestData
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
        if (count($result) === 0) {
            return null;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $requestData
     *
     * @throws InvalidSepRequest
     */
    public static function getTransactionsRequestFromRequestData(array $requestData): Sep24TransactionHistoryRequest
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
            if (is_int($requestData['limit'])) {
                $limit = $requestData['limit'];
            } else {
                throw new InvalidSepRequest('asset_code must be an integer');
            }
        }

        $kind = null;
        if (isset($requestData['kind'])) {
            if (is_string($requestData['kind'])) {
                $kind = $requestData['kind'];
                if ($kind !== 'deposit' && $kind !== 'withdrawal') {
                    throw new InvalidSepRequest('kind must be either deposit or withdrawal.');
                }
            } else {
                throw new InvalidSepRequest('kind must be a string');
            }
        }

        $pagingId = null;
        if (isset($requestData['paging_id'])) {
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

        return new Sep24TransactionHistoryRequest($assetCode, $noOlderThan, $limit, $kind, $pagingId, $lang);
    }
}
