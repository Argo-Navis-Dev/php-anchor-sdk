<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep31;

use ArgoNavis\PhpAnchorSdk\callback\IQuotesIntegration;
use ArgoNavis\PhpAnchorSdk\callback\Sep31PostTransactionRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep31PutTransactionCallbackRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep38PricesRequest;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\InvalidAsset;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\exception\QuoteNotFoundForId;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep31AssetInfo;
use ArgoNavis\PhpAnchorSdk\util\MemoHelper;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\Memo;

use function count;
use function filter_var;
use function floatval;
use function is_numeric;
use function is_string;
use function json_encode;
use function strval;
use function trim;

use const FILTER_VALIDATE_URL;

class Sep31RequestParser
{
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

    /**
     * Retrieves the post transaction request from the request data.
     *
     * @param string $accountId account id of the user obtained vis jwt token.
     * @param string|null $accountMemo account memo of the user obtained vis jwt token if any.
     * @param array<array-key, mixed> $requestData The request data.
     * @param array<Sep31AssetInfo> $supportedAssets The supported assets.
     * @param IQuotesIntegration|null $quotesIntegration SEP-38 Quotes integration if this anchor supports SEP-38 Quotes.
     *
     * @return Sep31PostTransactionRequest The post transaction request.
     *
     * @throws InvalidSepRequest
     * @throws AnchorFailure
     */
    public static function getPostTransactionRequestFromRequestData(
        string $accountId,
        ?string $accountMemo,
        array $requestData,
        array $supportedAssets,
        ?IQuotesIntegration $quotesIntegration = null,
    ): Sep31PostTransactionRequest {
        $amount = self::getValidatedFloat($requestData, 'amount');
        if ($amount === null) {
            throw new InvalidSepRequest('amount is required');
        }
        $asset = self::getValidatedAsset($requestData, $supportedAssets);

        // check if amount is allowed.
        self::validateAmount(
            requestAmount: $amount,
            assetCode: $asset->asset->getCode(),
            minAmount: $asset->minAmount,
            maxAmount: $asset->maxAmount,
        );

        /**
         * (optional) The off-chain asset the Receiving Anchor will deliver to the Receiving Client.
         * The value must match one of the asset values included in a
         * SEP-38 GET /prices?sell_asset=stellar:<asset_code>:<asset_issuer> response using SEP-38
         * Asset Identification Format. If neither this field nor quote_id are set,
         * it's assumed that Sending Anchor Asset Conversions was used.
         */
        $destinationAsset = self::getDestinationAssetFromRequestData($requestData);
        if ($destinationAsset !== null && $quotesIntegration !== null) {
            $pricesRequest = new Sep38PricesRequest(
                sellAsset: $asset->asset,
                sellAmount: strval($amount),
                accountId: $accountId,
                accountMemo: $accountMemo,
            );
            self::getLogger()->debug(
                'Obtaining the prices.',
                ['context' => 'sep31', 'prices_request_content' => json_encode($pricesRequest),
                    'operation' => 'new_transaction',
                ],
            );

            $sep38BuyAssets = $quotesIntegration->getPrices($pricesRequest);
            self::getLogger()->debug(
                'Prices obtained successfully.',
                ['context' => 'sep31', 'prices_content' => json_encode($sep38BuyAssets),
                    'operation' => 'new_transaction',
                ],
            );

            $buyAssetFound = false;
            foreach ($sep38BuyAssets as $sep38BuyAsset) {
                if ($sep38BuyAsset->asset->getCode() === $destinationAsset->getCode()) {
                    $buyAssetFound = true;

                    break;
                }
            }
            if (!$buyAssetFound) {
                self::getLogger()->debug(
                    'Buy asset not found in prices.',
                    ['context' => 'sep31', 'operation' => 'new_transaction',],
                );

                throw new InvalidSepRequest(
                    'invalid operation for asset ' .
                    $destinationAsset->getStringRepresentation(),
                );
            }
        } elseif ($destinationAsset !== null && $quotesIntegration === null) {
            throw new InvalidSepRequest('Destination asset not supported. Can not find price.');
        }

        $refundMemo = self::getRefundMemoFromRequestData($requestData);
        $lang = self::getRequestLang($requestData);

        /**
         * The id returned from a SEP-38 POST /quote response. If this attribute is specified,
         * the values for the fields defined above must match the values associated with the quote.
         */
        $quoteId = self::getValidatedQuoteId($requestData);
        if ($quoteId !== null && $quotesIntegration !== null) {
            try {
                $quote = $quotesIntegration->getQuoteById(
                    id: $quoteId,
                    accountId: $accountId,
                    accountMemo: $accountMemo,
                );
                if ($quote->sellAsset->getStringRepresentation() !== $asset->asset->getStringRepresentation()) {
                    self::getLogger()->debug(
                        'Quote sell asset does not match source asset.',
                        ['context' => 'sep31', 'operation' => 'new_transaction',
                            'quote_sell_asset' => $quote->sellAsset->getStringRepresentation(),
                            'source_asset' => $asset->asset->getStringRepresentation(),
                        ],
                    );

                    throw new InvalidSepRequest(
                        'quote sell asset does not match source_asset ' .
                        $asset->asset->getStringRepresentation(),
                    );
                }
                if (
                    $destinationAsset !== null &&
                    $quote->buyAsset->getStringRepresentation() !== $destinationAsset->getStringRepresentation()
                ) {
                    self::getLogger()->debug(
                        'Quote buy asset does not match destination asset.',
                        ['context' => 'sep31', 'operation' => 'new_transaction',
                            'quote_buy_asset' => $quote->buyAsset->getStringRepresentation(),
                            'destination_asset' => $destinationAsset->getCode(),
                        ],
                    );

                    throw new InvalidSepRequest(
                        'quote buy asset does not match destination_asset' .
                        $destinationAsset->getCode(),
                    );
                }

                if ($quote->sellAmount !== strval($amount)) {
                    if (!is_numeric($quote->sellAmount) || floatval($quote->sellAmount) !== $amount) {
                        self::getLogger()->debug(
                            'Quote amount does not match request amount.',
                            ['context' => 'sep31', 'operation' => 'new_transaction',
                                'amount' => $amount,
                                'quote_amount' => $quote->sellAmount,
                            ],
                        );

                        throw new InvalidSepRequest('quote amount does not match request amount');
                    }
                }
            } catch (QuoteNotFoundForId $qi) {
                self::getLogger()->debug(
                    'Quote not found.',
                    ['context' => 'sep31', 'operation' => 'new_transaction',
                        'id' => $quoteId, 'error' => $qi->getMessage(), 'exception' => $qi,
                    ],
                );

                throw new InvalidSepRequest($qi->getMessage());
            }
        } elseif ($quoteId !== null && $quotesIntegration === null) {
            self::getLogger()->debug(
                'Quote id is provided but quotes are not supported.',
                ['context' => 'sep31', 'operation' => 'new_transaction', 'id' => $quoteId],
            );

            throw new InvalidSepRequest('quote_id not supported. Can not find quote.');
        }

        /**
         * @var string|null $senderId (optional) The ID included in the SEP-12 PUT /customer response for the Sending Client.
         * Required if the Receiving Anchor requires SEP-12 KYC on the Sending Client.
         */
        $senderId = self::getValidatedStrValue($requestData, 'sender_id');
        if ($senderId === null && count($asset->sep12SenderTypes) > 0) {
            throw new InvalidSepRequest('sender_id is required');
        }

        /**
         * @var string|null $receiverId (optional) The ID included in the SEP-12 PUT /customer response for the
         * Receiving Client. Required if the Receiving Anchor requires SEP-12 KYC on the Receiving Client.
         */
        $receiverId = self::getValidatedStrValue($requestData, 'receiver_id');
        if ($receiverId === null && count($asset->sep12ReceiverTypes) > 0) {
            throw new InvalidSepRequest('receiver_id is required');
        }

        return new Sep31PostTransactionRequest(
            accountId: $accountId,
            accountMemo: $accountMemo,
            amount: $amount,
            asset: $asset,
            destinationAsset: $destinationAsset,
            quoteId: $quoteId,
            senderId: $senderId,
            receiverId: $receiverId,
            lang: $lang,
            refundMemo: $refundMemo,
        );
    }

    /**
     * Validates the asset code in the request data.
     *
     * @param array<array-key, mixed> $requestData The request data, expected to contain an 'asset_code' key.
     * @param array<Sep31AssetInfo> $supportedAssets The list of supported assets.
     *
     * @return Sep31AssetInfo The requested asset.
     *
     * @throws InvalidSepRequest If the request data regarding asset_code and asset_issuer is invalid,
     * or the requested asset is not supported.
     */
    private static function getValidatedAsset(
        array $requestData,
        array $supportedAssets,
    ): Sep31AssetInfo {
        $assetCode = null;
        $assetIssuer = null;
        if (isset($requestData['asset_code'])) {
            if (is_string($requestData['asset_code'])) {
                $assetCode = $requestData['asset_code'];
            } else {
                throw new InvalidSepRequest('asset_code must be a string');
            }
        } else {
            throw new InvalidSepRequest('asset_code is required');
        }

        if (isset($requestData['asset_issuer'])) {
            if (is_string($requestData['asset_issuer'])) {
                $assetIssuer = $requestData['asset_issuer'];
            } else {
                throw new InvalidSepRequest('asset_issuer must be a string');
            }
        }

        // check if asset code is supported.
        $asset = null;
        foreach ($supportedAssets as $supportedAsset) {
            if ($supportedAsset->asset->getCode() === $assetCode) {
                if ($assetIssuer !== null && $supportedAsset->asset->getIssuer() !== $assetIssuer) {
                    continue;
                }
                $asset = $supportedAsset;

                break;
            }
        }
        if ($asset === null) {
            throw new InvalidSepRequest('asset is not supported');
        }

        return $asset;
    }

    /**
     * @param array<array-key, mixed> $requestData The request data.
     * @param string $fieldKey The field key.
     *
     * @return float|null The validated float value, or null if not found.
     *
     * @throws InvalidSepRequest
     */
    public static function getValidatedFloat(
        array $requestData,
        string $fieldKey,
    ): ?float {
        $amount = null;
        if (isset($requestData[$fieldKey])) {
            if (is_numeric($requestData[$fieldKey])) {
                $amount = floatval($requestData[$fieldKey]);
                if ($amount <= 0.0) {
                    self::getLogger()->debug(
                        'Value must be greater than zero.',
                        ['context' => 'sep31', 'operation' => 'new_transaction',
                            'field' => $fieldKey, 'value' => $amount,
                        ],
                    );

                    throw new InvalidSepRequest($fieldKey . ' must be greater than zero');
                }
            } else {
                throw new InvalidSepRequest($fieldKey . ' must be a float');
            }
        }

        return $amount;
    }

    /**
     * @param array<array-key, mixed> $requestData The request data, expected to contain an 'asset_issuer' key.
     *
     * @return string|null The validated asset issuer, or null if not found.
     *
     * @throws InvalidSepRequest if asset issuer found is invalid.
     */
    public static function getValidatedAssetIssuer(
        array $requestData,
    ): ?string {
        if (isset($requestData['asset_issuer'])) {
            if (is_string($requestData['asset_issuer'])) {
                return trim($requestData['asset_issuer']);
            } else {
                throw new InvalidSepRequest('asset_issuer must be a string');
            }
        }

        return null;
    }

    /**
     * Extracts the quote_id if found.
     *
     * @param array<array-key, mixed> $requestData The request data, expected to contain a 'quote_id' key.
     *
     * @return string|null The validated quote ID, or null if not found.
     *
     * @throws InvalidSepRequest
     */
    public static function getValidatedQuoteId(
        array $requestData,
    ): ?string {
        if (isset($requestData['quote_id'])) {
            if (is_string($requestData['quote_id'])) {
                return trim($requestData['quote_id']);
            } else {
                throw new InvalidSepRequest('quote_id must be a string');
            }
        }

        return null;
    }

    /**
     * Creates a Sep31PutTransactionCallbackRequest from the given request data
     *
     * @param string $transactionId the requested transaction id.
     * @param array<array-key, mixed> $requestData the request data to parse the url from.
     * @param string $accountId account id of the sender anchor received by sep-10
     * @param string|null $accountMemo account memo of the sender anchor received by sep-10
     *
     * @return Sep31PutTransactionCallbackRequest if the request data is valid and the url was found.
     *
     * @throws InvalidSepRequest if the url was not found or invalid.
     */
    public static function getPutTransactionCallbackRequestData(
        string $transactionId,
        array $requestData,
        string $accountId,
        ?string $accountMemo = null,
    ): Sep31PutTransactionCallbackRequest {
        $url = null;
        if (isset($requestData['url'])) {
            self::getLogger()->debug(
                'Validating the passed URL.',
                ['context' => 'sep31', 'operation' => 'put_transaction_callback', 'url' => $requestData['url']],
            );
            if (is_string($requestData['url'])) {
                $url = $requestData['url'];
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new InvalidSepRequest('invalid url');
                }
            } else {
                throw new InvalidSepRequest('url must be a string');
            }
        }

        if ($url === null || trim($url) === '') {
            throw new InvalidSepRequest('url is mandatory');
        }

        return new Sep31PutTransactionCallbackRequest($transactionId, $accountId, $accountMemo, $url);
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
        self::getLogger()->debug(
            'Validating amount.',
            ['context' => 'sep31', 'min_amount' => $minAmount, 'max_amount' => $maxAmount,
                'request_amount' => $requestAmount, 'operation' => 'new_transaction',
            ],
        );

        if (
            $requestAmount <= 0 ||
            ($minAmount !== null && $requestAmount < $minAmount) ||
            ($maxAmount !== null && $requestAmount > $maxAmount)
        ) {
            throw new InvalidSepRequest('invalid amount ' . strval($requestAmount) .
                ' for asset ' . $assetCode);
        }
    }

    /**
     * Extracts the refund memo from the request data and returns it as a Memo object.
     *
     * @param array<array-key, mixed> $requestData the array with the request data.
     *
     * @return Memo|null the refund memo as a Memo object or null if not present.
     *
     * @throws InvalidSepRequest if the memo data is invalid.
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
     * Extracts the lang query parameter from the request.
     *
     * @param array<array-key, mixed> $queryParams the array with the query parameters from the request.
     *
     * @return string|null the lang query parameter or null if not present.
     *
     * @throws InvalidSepRequest if the lang query parameter is not a string.
     */
    public static function getRequestLang(array $queryParams): ?string
    {
        $lang = null;
        if (isset($queryParams['lang'])) {
            if (is_string($queryParams['lang'])) {
                $lang = $queryParams['lang'];
            } else {
                throw new InvalidSepRequest('lang must be a string');
            }
        }

        return $lang;
    }

    /**
     * Validates and returns a string value from the request data.
     *
     * @param array<array-key, mixed> $requestData the array with the request data.
     * @param string $fieldKey the key of the field to retrieve from the request data.
     *
     * @return string|null the validated string value or null if not present.
     *
     * @throws InvalidSepRequest if the field value is not a string.
     */
    public static function getValidatedStrValue(
        array $requestData,
        string $fieldKey,
    ): ?string {
        if (isset($requestData[$fieldKey])) {
            if (is_string($requestData[$fieldKey])) {
                return trim($requestData[$fieldKey]);
            } else {
                throw new InvalidSepRequest($fieldKey . ' must be a string');
            }
        }

        return null;
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
                        ['context' => 'sep31', 'operation' => 'new_transaction',
                            'error' => $invalidAsset->getMessage(), 'exception' => $invalidAsset,
                        ],
                    );

                    throw new InvalidSepRequest('invalid destination asset: ' . $invalidAsset->getMessage());
                }
            } else {
                throw new InvalidSepRequest('destination asset must be a string');
            }
        }

        return $destinationAsset;
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
