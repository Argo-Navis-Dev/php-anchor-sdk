<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.


namespace ArgoNavis\Test\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\callback\IQuotesIntegration;
use ArgoNavis\PhpAnchorSdk\callback\Sep38PriceRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep38PricesRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep38QuoteRequest;
use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\InvalidAsset;
use ArgoNavis\PhpAnchorSdk\exception\QuoteNotFoundForId;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep38AssetInfo;
use ArgoNavis\PhpAnchorSdk\shared\Sep38BuyAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep38DeliveryMethod;
use ArgoNavis\PhpAnchorSdk\shared\Sep38Price;
use ArgoNavis\PhpAnchorSdk\shared\Sep38Quote;
use ArgoNavis\PhpAnchorSdk\shared\TransactionFeeInfo;
use ArgoNavis\PhpAnchorSdk\shared\TransactionFeeInfoDetail;
use DateTime;

use const DATE_ATOM;

class QuotesIntegration implements IQuotesIntegration
{
    public static string $stellarUSDCStr = 'stellar:USDC:GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN';
    public static string $stellarBRLStr = 'stellar:BRL:GDVKY2GU2DRXWTBEYJJWSFXIGBZV6AZNBVVSUHEPZI54LIS6BA7DVVSP';
    public static string $iso4217BRLStr = 'iso4217:BRL';

    /**
     * @inheritDoc
     */
    public function supportedAssets(?string $accountId = null, ?string $accountMemo = null): array
    {
        return self::composeSupportedAssets();
    }

    /**
     * @inheritDoc
     */
    public function getPrices(Sep38PricesRequest $request): array
    {
        try {
            if ($request->sellAsset->getStringRepresentation() === self::$stellarUSDCStr) {
                return [
                    new Sep38BuyAsset(
                        asset: IdentificationFormatAsset::fromString(asset: self::$iso4217BRLStr),
                        price: '0.18',
                        decimals: 2,
                    ),
                ];
            } elseif ($request->sellAsset->getStringRepresentation() === self::$iso4217BRLStr) {
                return [
                    new Sep38BuyAsset(
                        asset: IdentificationFormatAsset::fromString(
                            asset: self::$stellarUSDCStr,
                        ),
                        price: '5.42',
                        decimals: 7,
                    ),
                ];
            } else {
                return [];
            }
        } catch (InvalidAsset $e) {
            throw new AnchorFailure('Error getting prices: ' . $e->getMessage());
        }
    }

    public function getPrice(Sep38PriceRequest $request): Sep38Price
    {
        try {
            if (
                $request->sellAsset->getStringRepresentation() === self::$iso4217BRLStr &&
                $request->buyAsset->getStringRepresentation() === self::$stellarUSDCStr &&
                $request->sellAmount === '500' &&
                $request->sellDeliveryMethod === 'PIX' &&
                $request->countryCode === 'BRA' &&
                $request->context === 'sep6'
            ) {
                return new Sep38Price(
                    totalPrice: '5.42',
                    price: '5.00',
                    sellAmount: '542',
                    buyAmount: '100',
                    fee: new TransactionFeeInfo(
                        total: '42.00',
                        asset: IdentificationFormatAsset::fromString(self::$iso4217BRLStr),
                    ),
                );
            } elseif (
                $request->sellAsset->getStringRepresentation() === self::$iso4217BRLStr &&
                $request->buyAsset->getStringRepresentation() === self::$stellarUSDCStr &&
                $request->buyAmount === '100' &&
                $request->sellDeliveryMethod === 'PIX' &&
                $request->countryCode === 'BRA' &&
                $request->context === 'sep31'
            ) {
                return new Sep38Price(
                    totalPrice: '5.42',
                    price: '5.00',
                    sellAmount: '542',
                    buyAmount: '100',
                    fee: new TransactionFeeInfo(
                        total: '8.40',
                        asset: IdentificationFormatAsset::fromString(self::$stellarUSDCStr),
                        details: [new TransactionFeeInfoDetail(name: 'Service fee', amount: '8.40')],
                    ),
                );
            } elseif (
                $request->sellAsset->getStringRepresentation() === self::$stellarUSDCStr &&
                $request->buyAsset->getStringRepresentation() === self::$iso4217BRLStr &&
                $request->sellAmount === '90' &&
                $request->buyDeliveryMethod === 'PIX' &&
                $request->countryCode === 'BRA' &&
                $request->context === 'sep6'
            ) {
                return new Sep38Price(
                    totalPrice: '0.20',
                    price: '0.18',
                    sellAmount: '100',
                    buyAmount: '500',
                    fee: new TransactionFeeInfo(
                        total: '55.5556',
                        asset: IdentificationFormatAsset::fromString(self::$iso4217BRLStr),
                        details: [
                            new TransactionFeeInfoDetail(
                                name: 'PIX fee',
                                amount: '55.5556',
                                description: 'Fee charged in order to process the outgoing PIX transaction.',
                            ),
                        ],
                    ),
                );
            } elseif (
                $request->sellAsset->getStringRepresentation() === self::$stellarUSDCStr &&
                $request->buyAsset->getStringRepresentation() === self::$iso4217BRLStr &&
                $request->buyAmount === '500' &&
                $request->buyDeliveryMethod === 'PIX' &&
                $request->countryCode === 'BRA' &&
                $request->context === 'sep31'
            ) {
                return new Sep38Price(
                    totalPrice: '0.20',
                    price: '0.18',
                    sellAmount: '100',
                    buyAmount: '500',
                    fee: new TransactionFeeInfo(
                        total: '10.00',
                        asset: IdentificationFormatAsset::fromString(self::$stellarUSDCStr),
                        details: [
                            new TransactionFeeInfoDetail(name: 'Service fee', amount: '5.00'),
                            new TransactionFeeInfoDetail(
                                name: 'PIX fee',
                                amount: '5.00',
                                description: 'Fee charged in order to process the outgoing BRL PIX transaction.',
                            ),
                        ],
                    ),
                );
            } else {
                throw new AnchorFailure('Error getting price: invalid test request');
            }
        } catch (InvalidAsset $e) {
            throw new AnchorFailure('Error getting prices: ' . $e->getMessage());
        }
    }

    public function getQuote(Sep38QuoteRequest $request): Sep38Quote
    {
        return self::composeQuote();
    }

    /**
     * @throws AnchorFailure
     * @throws QuoteNotFoundForId
     */
    public function getQuoteById(string $id, string $accountId, ?string $accountMemo = null): Sep38Quote
    {
        if ($id === 'de762cda-a193-4961-861e-57b31fed6eb3') {
            return self::composeQuote();
        } elseif ($id === 'sep6test-a193-4961-861e-57b31fed6eb3') {
            try {
                // for sep6 withdraw exchange test.
                $quote = self::composeQuote();
                $quote->sellAsset = IdentificationFormatAsset::fromString(self::$stellarUSDCStr);
                $quote->buyAsset = IdentificationFormatAsset::fromString(self::$iso4217BRLStr);

                return $quote;
            } catch (InvalidAsset $e) {
                throw new AnchorFailure('Error composing quote: ' . $e->getMessage());
            }
        } else {
            throw new QuoteNotFoundForId(id:$id);
        }
    }

    /**
     * Composes a quote for testing.
     *
     * @return Sep38Quote the composed quote.
     *
     * @throws AnchorFailure
     */
    private static function composeQuote(): Sep38Quote
    {
        $expiresAt = DateTime::createFromFormat(DATE_ATOM, '2025-04-30T07:42:23Z');
        if ($expiresAt === false) {
            throw new AnchorFailure('Error composing quote: invalid date format');
        }
        try {
            return new Sep38Quote(
                id: 'de762cda-a193-4961-861e-57b31fed6eb3',
                expiresAt: $expiresAt,
                totalPrice: '5.42',
                price: '5.00',
                sellAsset: IdentificationFormatAsset::fromString(self::$iso4217BRLStr),
                sellAmount: '542',
                buyAsset: IdentificationFormatAsset::fromString(self::$stellarUSDCStr),
                buyAmount: '100',
                fee: new TransactionFeeInfo(
                    total: '42.00',
                    asset: IdentificationFormatAsset::fromString(self::$iso4217BRLStr),
                    details: [
                        new TransactionFeeInfoDetail(
                            name: 'PIX fee',
                            amount: '12.00',
                            description: 'Fee charged in order to process the outgoing PIX transaction.',
                        ),
                        new TransactionFeeInfoDetail(
                            name: 'Brazilian conciliation fee',
                            amount: '15.00',
                            description: 'Fee charged in order to process conciliation costs with intermediary banks.',
                        ),
                        new TransactionFeeInfoDetail(name: 'Service fee', amount: '15.00'),
                    ],
                ),
            );
        } catch (InvalidAsset $e) {
            throw new AnchorFailure('Error composing quote: ' . $e->getMessage());
        }
    }

    /**
     * Composes the supported assets for testing.
     *
     * @return array<Sep38AssetInfo> the supported assets.
     *
     * @throws AnchorFailure
     */
    private static function composeSupportedAssets(): array
    {
        try {
            $countryCodes = ['BRA'];

            $stellarUSDC = new Sep38AssetInfo(
                asset: IdentificationFormatAsset::fromString(
                    asset:self::$stellarUSDCStr,
                ),
                countryCodes: $countryCodes,
            );
            $stellarBRL = new Sep38AssetInfo(
                asset: IdentificationFormatAsset::fromString(
                    asset:self::$stellarBRLStr,
                ),
            );

            // iso4217:BRL
            $sellDeliveryMethods = [
                new Sep38DeliveryMethod(name: 'cash', description: 'Deposit cash BRL at one of our agent locations.'),
                new Sep38DeliveryMethod(name: 'ACH', description: "Send BRL directly to the Anchor's bank account."),
                new Sep38DeliveryMethod(name: 'PIX', description: "Send BRL directly to the Anchor's bank account."),
            ];

            $buyDeliveryMethods = [
                new Sep38DeliveryMethod(name: 'cash', description: 'Pick up cash BRL at one of our payout locations.'),
                new Sep38DeliveryMethod(name: 'ACH', description: 'Have BRL sent directly to your bank account.'),
                new Sep38DeliveryMethod(
                    name: 'PIX',
                    description: 'Have BRL sent directly to the account of your choice.',
                ),
            ];

            $iso4217BRL = new Sep38AssetInfo(
                asset: IdentificationFormatAsset::fromString(self::$iso4217BRLStr),
                sellDeliveryMethods: $sellDeliveryMethods,
                buyDeliveryMethods: $buyDeliveryMethods,
                countryCodes: $countryCodes,
            );

            return [$stellarUSDC, $stellarBRL, $iso4217BRL];
        } catch (InvalidAsset $e) {
            throw new AnchorFailure('Error composing supported assets: ' . $e->getMessage());
        }
    }
}
