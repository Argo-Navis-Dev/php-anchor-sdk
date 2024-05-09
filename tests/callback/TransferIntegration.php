<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\callback\ITransferIntegration;
use ArgoNavis\PhpAnchorSdk\callback\Sep06TransactionResponse;
use ArgoNavis\PhpAnchorSdk\callback\StartDepositExchangeRequest;
use ArgoNavis\PhpAnchorSdk\callback\StartDepositRequest;
use ArgoNavis\PhpAnchorSdk\callback\StartDepositResponse;
use ArgoNavis\PhpAnchorSdk\callback\StartWithdrawExchangeRequest;
use ArgoNavis\PhpAnchorSdk\callback\StartWithdrawRequest;
use ArgoNavis\PhpAnchorSdk\callback\StartWithdrawResponse;
use ArgoNavis\PhpAnchorSdk\callback\TransactionHistoryRequest;
use ArgoNavis\PhpAnchorSdk\exception\InvalidAsset;
use ArgoNavis\PhpAnchorSdk\shared\DepositOperation;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\InstructionsField;
use ArgoNavis\PhpAnchorSdk\shared\Sep06AssetInfo;
use ArgoNavis\PhpAnchorSdk\shared\Sep06InfoField;
use ArgoNavis\PhpAnchorSdk\shared\Sep06TransactionStatus;
use ArgoNavis\PhpAnchorSdk\shared\TransactionFeeInfo;
use ArgoNavis\PhpAnchorSdk\shared\TransactionFeeInfoDetail;
use ArgoNavis\PhpAnchorSdk\shared\TransactionRefundPayment;
use ArgoNavis\PhpAnchorSdk\shared\TransactionRefunds;
use ArgoNavis\PhpAnchorSdk\shared\WithdrawOperation;
use DateTime;

use function PHPUnit\Framework\assertNotNull;
use function uniqid;

class TransferIntegration implements ITransferIntegration
{
    /**
     * @var array<Sep06AssetInfo> $supportedAssets
     */
    public array $supportedAssets;

    public function __construct()
    {
        $this->supportedAssets = self::composeSupportedAssets();
    }

    /**
     * @inheritDoc
     */
    public function supportedAssets(): array
    {
        return $this->supportedAssets;
    }

    /**
     * @return array<Sep06AssetInfo>
     */
    private static function composeSupportedAssets(): array
    {
        $usd = new IdentificationFormatAsset(
            schema: IdentificationFormatAsset::ASSET_SCHEMA_STELLAR,
            code:'USDC',
            issuer: 'GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN',
        );
        $eth = new IdentificationFormatAsset(
            schema: IdentificationFormatAsset::ASSET_SCHEMA_STELLAR,
            code: 'ETH',
            issuer: 'GDLW7I64UY2HG4PWVJB2KYLG5HWPRCIDD3WUVRFJMJASX4CB7HJVDOYP',
        );

        $usdDepositOp = new DepositOperation(
            enabled: true,
            minAmount: 0.1,
            maxAmount: 1000,
            methods: ['SEPA', 'SWIFT', 'cash'],
        );
        $usdWithdrawOp = new WithdrawOperation(
            enabled: true,
            minAmount: 0.1,
            maxAmount: 1000,
            methods: ['bank_account', 'cash'],
        );
        $usdInfo = new Sep06AssetInfo(
            asset: $usd,
            depositOperation: $usdDepositOp,
            withdrawOperation: $usdWithdrawOp,
            depositExchangeEnabled: true,
            withdrawExchangeEnabled: true,
        );

        $ethDepositOp = new DepositOperation(enabled: true);
        $ethWithdrawOp = new WithdrawOperation(enabled: false);
        $ethInfo = new Sep06AssetInfo(
            asset: $eth,
            depositOperation: $ethDepositOp,
            withdrawOperation: $ethWithdrawOp,
            depositExchangeEnabled: false,
            withdrawExchangeEnabled: false,
        );

        return [$usdInfo, $ethInfo];
    }

    public function deposit(StartDepositRequest $request): StartDepositResponse
    {
        $instructions = [
            new InstructionsField(name: 'bank_number', value: '121122676', description: 'US bank routing number'),
            new InstructionsField(
                name: 'bank_account_number',
                value: '13719713158835300',
                description: 'US bank account number',
            ),
        ];

        return new StartDepositResponse(
            id: uniqid(),
            instructions: $instructions,
            eta:45,
            minAmount: 0.1,
            maxAmount: 1000.0,
            feeFixed: 0.5,
            feePercent: 2.0,
            extraInfo: 'extra info test',
            how:'Make a payment to Bank: 121122676 Account: 13719713158835300',
        );
    }

    public function depositExchange(StartDepositExchangeRequest $request): StartDepositResponse
    {
        $instructions = [
            new InstructionsField(name: 'bank_number', value: '121122676', description: 'US bank routing number'),
            new InstructionsField(
                name: 'bank_account_number',
                value: '13719713158835300',
                description: 'US bank account number',
            ),
        ];

        return new StartDepositResponse(
            id: uniqid(),
            instructions: $instructions,
            eta:45,
            minAmount: 0.1,
            maxAmount: 1000.0,
            feeFixed: 0.5,
            feePercent: 2.0,
            extraInfo: 'extra info test',
            how:'Make a payment to Bank: 121122676 Account: 13719713158835300',
        );
    }

    public function withdraw(StartWithdrawRequest $request): StartWithdrawResponse
    {
        return new StartWithdrawResponse(
            id: uniqid(),
            accountId: 'GCKKKJW2RY2YWZGEW7SSY3H7J2MMHYSEQQ7JOHBWORYBGCB2ZDKJ6VFA',
            memoType: 'id',
            memo: '19233',
            eta: 45,
            minAmount: 0.1,
            maxAmount: 1000.00,
            feeFixed: 0.5,
            feePercent: 0.2,
            extraInfo: 'extra test',
        );
    }

    public function withdrawExchange(StartWithdrawExchangeRequest $request): StartWithdrawResponse
    {
        return new StartWithdrawResponse(
            id: uniqid(),
            accountId: 'GCKKKJW2RY2YWZGEW7SSY3H7J2MMHYSEQQ7JOHBWORYBGCB2ZDKJ6VFA',
            memoType: 'id',
            memo: '19233',
            eta: 45,
            minAmount: 0.1,
            maxAmount: 1000.00,
            feeFixed: 0.5,
            feePercent: 0.2,
            extraInfo: 'extra test',
        );
    }

    public function findTransactionById(
        string $id,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep06TransactionResponse {
        return $this->composeTestTransaction(id:$id, account: $accountId);
    }

    public function findTransactionByStellarTransactionId(
        string $stellarTransactionId,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep06TransactionResponse {
        return $this->composeTestTransaction(id:uniqid(), account: $accountId, stellarTxId: $stellarTransactionId);
    }

    public function findTransactionByExternalTransactionId(
        string $externalTransactionId,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep06TransactionResponse {
        return $this->composeTestTransaction(id:uniqid(), account: $accountId, externalTxId: $externalTransactionId);
    }

    /**
     * @inheritDoc
     */
    public function getTransactionHistory(
        TransactionHistoryRequest $request,
        string $accountId,
        ?string $accountMemo = null,
    ): ?array {
        $tx1 = $this->composeTestTransaction(id:uniqid(), account: $accountId);
        assertNotNull($tx1);
        $tx2 = $this->composeTestTransaction(id:uniqid(), account: $accountId);
        assertNotNull($tx2);
        $tx3 = $this->composeTestTransaction(id:uniqid(), account: $accountId);
        assertNotNull($tx3);

        return [$tx1, $tx2, $tx3];
    }

    private function composeTestTransaction(
        string $id,
        string $account,
        ?string $stellarTxId = null,
        ?string $externalTxId = null,
    ): ?Sep06TransactionResponse {
        try {
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
                new Sep06InfoField(
                    fieldName: 'country_code',
                    description: "The ISO 3166-1 alpha-3 code of the user's current address",
                    choices: ['USA', 'BRA'],
                    optional: false,
                ),
                new Sep06InfoField(
                    fieldName: 'dest',
                    description: 'your bank account number',
                ),
            ];

            $instructions = [
                new InstructionsField(name: 'bank_number', value: '121122676', description: 'US bank routing number'),
                new InstructionsField(
                    name: 'bank_account_number',
                    value: '13719713158835300',
                    description: 'US bank account number',
                ),
            ];

            return new Sep06TransactionResponse(
                id: $id,
                kind: 'withdraw',
                status: Sep06TransactionStatus::COMPLETED,
                statusEta: 2500,
                moreInfoUrl: 'https://test.com/more/' . $id,
                amountIn: '100.0',
                amountInAsset: IdentificationFormatAsset::fromString(
                    'stellar:USDC:GA5ZSEJYB37JRC5AVCIA5MOP4RHTM335X2KGX3IHOJAPP5RE34K4KZVN',
                ),
                amountOut: '110.0',
                amountOutAsset: IdentificationFormatAsset::fromString('iso4217:BRL'),
                feeDetails: $feeDetails,
                quoteId: 'sep6test-a193-4961-861e-57b31fed6eb3',
                from: $account,
                to: 'GB29 NWBK 6016 1331 9268 19',
                externalExtra: 'external extra test',
                externalExtraText: 'external extra text test',
                depositMemo: 'deposit-123',
                depositMemoType: 'text',
                withdrawAnchorAccount: 'GDV2NKAAB5KXKGB7L65HOQ5XEGLXRTUGQET5JH2CNWSUIAHQH3FH7AN3',
                withdrawMemo: '771626434',
                withdrawMemoType: 'id',
                startedAt: new DateTime('now'),
                completedAt: new DateTime('now'),
                updatedAt: new DateTime('now'),
                stellarTransactionId: $stellarTxId,
                externalTransactionId: $externalTxId,
                message: 'hi',
                refunds: $refunds,
                requiredInfoMessage: 'test required info message',
                requiredInfoUpdates: $requiredInfoUpdates,
                instructions: $instructions,
                claimableBalanceId: '000000000a12cd57c169a34e7794bdcdf2d093fab135c59ea599e2d1233d7a53f26c1464',
            );
        } catch (InvalidAsset) {
            return null;
        }
    }
}
