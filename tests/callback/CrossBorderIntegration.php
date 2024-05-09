<?php

declare(strict_types=1);

namespace ArgoNavis\Test\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\callback\ICrossBorderIntegration;
use ArgoNavis\PhpAnchorSdk\callback\Sep31PostTransactionRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep31PostTransactionResponse;
use ArgoNavis\PhpAnchorSdk\callback\Sep31PutTransactionCallbackRequest;
use ArgoNavis\PhpAnchorSdk\callback\Sep31TransactionResponse;
use ArgoNavis\PhpAnchorSdk\exception\Sep31TransactionCallbackNotSupported;
use ArgoNavis\PhpAnchorSdk\exception\Sep31TransactionNotFoundForId;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep12Type;
use ArgoNavis\PhpAnchorSdk\shared\Sep31AssetInfo;
use ArgoNavis\PhpAnchorSdk\shared\Sep31InfoField;
use ArgoNavis\PhpAnchorSdk\shared\Sep31TransactionStatus;
use ArgoNavis\PhpAnchorSdk\shared\TransactionFeeInfo;
use ArgoNavis\PhpAnchorSdk\shared\TransactionFeeInfoDetail;
use ArgoNavis\PhpAnchorSdk\shared\TransactionRefundPayment;
use ArgoNavis\PhpAnchorSdk\shared\TransactionRefunds;
use DateTime;

class CrossBorderIntegration implements ICrossBorderIntegration
{
    /**
     * @var array<Sep31AssetInfo> $supportedAssets
     */
    public array $supportedAssets;

    public function __construct()
    {
        $this->supportedAssets = self::composeSupportedAssets();
    }

    /**
     * @inheritDoc
     */
    public function supportedAssets(string $accountId, ?string $accountMemo = null, ?string $lang = null): array
    {
        return $this->supportedAssets;
    }

    public function postTransaction(Sep31PostTransactionRequest $request): Sep31PostTransactionResponse
    {
        return new Sep31PostTransactionResponse(
            id:'9bff0aff-e8fb-47a7-81bb-0b776501cbb6',
            stellarAccountId: 'GDV2NKAAB5KXKGB7L65HOQ5XEGLXRTUGQET5JH2CNWSUIAHQH3FH7AN3',
            stellarMemoType: 'id',
            stellarMemo: '120190893',
        );
    }

    public function getTransactionById(
        string $id,
        string $accountId,
        ?string $accountMemo = null,
    ): Sep31TransactionResponse {
        if ($id !== '9bff0aff-e8fb-47a7-81bb-0b776501cbb6') {
            throw new Sep31TransactionNotFoundForId($id);
        }

        $feeDetails = new TransactionFeeInfo(
            total: '10',
            asset: IdentificationFormatAsset::fromString('iso4217:BRL'),
            details: [
                new TransactionFeeInfoDetail(name: 'Fun fee', amount: '6.5', description: 'just for fun'),
                new TransactionFeeInfoDetail(name: 'Service fee', amount: '3.5', description: 'for the service'),
            ],
        );

        $refunds = new TransactionRefunds(
            amountRefunded: '10',
            amountFee: '2.0',
            payments: [
                new TransactionRefundPayment(id: '104201', idType: 'external', amount: '5', fee: '1.0'),
                new TransactionRefundPayment(id: '104202', idType: 'external', amount: '5', fee: '1.0'),
            ],
        );

        $requiredInfoUpdates = [
            new Sep31InfoField(
                fieldName: 'country_code',
                description: "The ISO 3166-1 alpha-3 code of the user's current address",
                choices: ['USA', 'BRA'],
                optional: false,
            ),
            new Sep31InfoField(
                fieldName: 'dest',
                description: 'your bank account number',
            ),
        ];

        return new Sep31TransactionResponse(
            id: '9bff0aff-e8fb-47a7-81bb-0b776501cbb6',
            status: Sep31TransactionStatus::PENDING_SENDER,
            feeDetails: $feeDetails,
            statusEta: 2500,
            statusMessage: 'status message',
            amountIn: '100.0',
            amountInAsset: IdentificationFormatAsset::fromString(
                'stellar:USDC:GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN',
            ),
            amountOut: '110.0',
            amountOutAsset: IdentificationFormatAsset::fromString('iso4217:BRL'),
            quoteId: 'sep6test-a193-4961-861e-57b31fed6eb3',
            stellarAccountId: 'GDV2NKAAB5KXKGB7L65HOQ5XEGLXRTUGQET5JH2CNWSUIAHQH3FH7AN3',
            stellarMemoType: 'id',
            stellarMemo: '120190893',
            startedAt: new DateTime('now'),
            updatedAt: new DateTime('now'),
            completedAt: new DateTime('now'),
            stellarTransactionId: '1234',
            externalTransactionId: '5678',
            refunds: $refunds,
            requiredInfoMessage: 'required info message',
            requiredInfoUpdates: $requiredInfoUpdates,
        );
    }

    public function putTransactionCallback(Sep31PutTransactionCallbackRequest $request): void
    {
        if ($request->url === 'https://notsupported.com') {
            throw new Sep31TransactionCallbackNotSupported('this endpoint is not supported');
        }
    }

    /**
     * Composes a list of supported assets.
     *
     * @return array<Sep31AssetInfo> the supported assets.
     */
    private static function composeSupportedAssets(): array
    {
        $usdc = new IdentificationFormatAsset(
            schema: IdentificationFormatAsset::ASSET_SCHEMA_STELLAR,
            code:'USDC',
            issuer: 'GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN',
        );
        $eth = new IdentificationFormatAsset(
            schema: IdentificationFormatAsset::ASSET_SCHEMA_STELLAR,
            code: 'ETH',
            issuer: 'GDLW7I64UY2HG4PWVJB2KYLG5HWPRCIDD3WUVRFJMJASX4CB7HJVDOYP',
        );

        $senderTypes = [
            new Sep12Type(
                name:'sep31-sender',
                description: 'U.S. citizens limited to sending payments of less than $10,000 in value',
            ),
            new Sep12Type(
                name:'sep31-large-sender',
                description: 'U.S. citizens that do not have sending limits',
            ),
            new Sep12Type(
                name:'sep31-foreign-sender',
                description: 'non-U.S. citizens sending payments of less than $10,000 in value',
            ),
        ];

        $receiverTypes = [
            new Sep12Type(
                name:'sep31-receiver',
                description: 'U.S. citizens receiving USD',
            ),
            new Sep12Type(
                name:'sep31-foreign-receiver',
                description: 'non-U.S. citizens receiving USD',
            ),
        ];

        $usdcSep31Asset = new Sep31AssetInfo(
            asset: $usdc,
            sep12SenderTypes: $senderTypes,
            sep12ReceiverTypes: $receiverTypes,
            minAmount: 0.1,
            maxAmount: 1000,
            feeFixed: 5,
            feePercent: 1,
            quotesSupported: true,
            quotesRequired: false,
        );

        $ethSep31Asset = new Sep31AssetInfo(
            asset: $eth,
            sep12SenderTypes: $senderTypes,
            sep12ReceiverTypes: $receiverTypes,
            minAmount: 0.1,
            maxAmount: 1000,
            feeFixed: 5,
            feePercent: 1,
            quotesSupported: true,
            quotesRequired: false,
        );

        return [$usdcSep31Asset, $ethSep31Asset];
    }
}
