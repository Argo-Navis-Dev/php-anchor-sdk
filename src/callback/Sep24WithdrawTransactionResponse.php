<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.


namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep24Refunds;
use DateTime;

class Sep24WithdrawTransactionResponse extends Sep24TransactionResponse
{
    /**
     * @var string $from Stellar address the assets were withdrawn from.
     */
    public string $from;

    /**
     * @var string $to Sent to address (perhaps BTC, IBAN, or bank account).
     */
    public string $to;

    /**
     * @var string $withdrawAnchorAccount If this is a withdrawal, this is the anchor's Stellar account that the user transferred (or will transfer) their asset to.
     */
    public string $withdrawAnchorAccount;

    /**
     * @var string|null $withdrawMemo Memo used when the user transferred to withdrawAnchorAccount. Assigned null if the withdraw is not ready to receive payment, for example if KYC is not completed.
     */
    public ?string $withdrawMemo = null;

    /**
     * @var string|null $withdrawMemoType Memo type for withdrawMemo.
     */
    public ?string $withdrawMemoType = null;

    /**
     * @param string $id Unique, anchor-generated id for the withdrawal.
     * @param string $from Stellar address the assets were withdrawn from.
     * @param string $to Sent to address (perhaps BTC, IBAN, or bank account).
     * @param string $withdrawAnchorAccount If this is a withdrawal, this is the anchor's Stellar account that the user transferred (or will transfer) their asset to.
     * @param string $status Processing status of withdrawal. (see Sep24TransactionStatus)
     * @param string $moreInfoUrl A URL that is opened by wallets after the interactive flow is complete. It can include banking information for users to start deposits, the status of the transaction, or any other information the user might need to know about the transaction.
     * @param string $amountIn Amount received by anchor at start of transaction as a string with up to 7 decimals. Excludes any fees charged before the anchor received the funds.
     * @param string $amountOut Amount sent by anchor to user at end of transaction as a string with up to 7 decimals. Excludes amount converted to XLM to fund account and any external fees.
     * @param string $amountFee Amount of fee charged by anchor.
     * @param DateTime $startedAt Start date and time of transaction.
     * @param string|null $stellarTransactionId transaction id on Stellar network of the transfer that started the withdrawal.
     * @param string|null $withdrawMemo Memo used when the user transferred to withdraw_anchor_account. Assigned null if the withdraw is not ready to receive payment, for example if KYC is not completed.
     * @param string|null $withdrawMemoType Type for the withdrawMemo.
     * @param int|null $statusEta Estimated number of seconds until a status change is expected.
     * @param bool|null $kycVerified true, if the anchor has verified the user's KYC information for this transaction.
     * @param IdentificationFormatAsset|null $amountInAsset The asset received or to be received by the Anchor. Must be present if the withdrawal was made using non-equivalent assets.
     * @param IdentificationFormatAsset|null $amountOutAsset The asset delivered or to be delivered to the user. Must be present if the withdrawal was made using non-equivalent assets.
     * @param IdentificationFormatAsset|null $amountFeeAsset The asset in which fees are calculated in. Must be present if the withdrawal was made using non-equivalent assets.
     * @param string|null $quoteId The ID of the quote used when creating this transaction.
     * @param DateTime|null $completedAt The date and time of transaction reaching completed or refunded status.
     * @param DateTime|null $updatedAt The date and time of transaction reaching the current status.
     * @param string|null $externalTransactionId ID of transaction on external network that completed the withdrawal.
     * @param string|null $message Human-readable explanation of transaction status, if needed.
     * @param bool|null $refunded True if the transaction was refunded in full. False if the transaction was partially refunded or not refunded.
     * @param Sep24Refunds|null $refunds An object describing any on or off-chain refund associated with this transaction.
     */
    public function __construct(
        string $id,
        string $from,
        string $to,
        string $withdrawAnchorAccount,
        string $status,
        string $moreInfoUrl,
        string $amountIn,
        string $amountOut,
        string $amountFee,
        DateTime $startedAt,
        ?string $stellarTransactionId = null,
        ?string $withdrawMemo = null,
        ?string $withdrawMemoType = null,
        ?int $statusEta = null,
        ?bool $kycVerified = null,
        ?IdentificationFormatAsset $amountInAsset = null,
        ?IdentificationFormatAsset $amountOutAsset = null,
        ?IdentificationFormatAsset $amountFeeAsset = null,
        ?string $quoteId = null,
        ?DateTime $completedAt = null,
        ?DateTime $updatedAt = null,
        ?string $externalTransactionId = null,
        ?string $message = null,
        ?bool $refunded = null,
        ?Sep24Refunds $refunds = null,
    ) {
        parent::__construct(
            id:$id,
            kind:'withdrawal',
            status:$status,
            moreInfoUrl: $moreInfoUrl,
            amountIn: $amountIn,
            amountOut: $amountOut,
            amountFee: $amountFee,
            startedAt: $startedAt,
            stellarTransactionId: $stellarTransactionId,
            statusEta: $statusEta,
            kycVerified: $kycVerified,
            amountInAsset: $amountInAsset,
            amountOutAsset: $amountOutAsset,
            amountFeeAsset: $amountFeeAsset,
            quoteId: $quoteId,
            completedAt: $completedAt,
            updatedAt: $updatedAt,
            externalTransactionId: $externalTransactionId,
            message: $message,
            refunded: $refunded,
            refunds: $refunds,
        );

        $this->from = $from;
        $this->to = $to;
        $this->withdrawAnchorAccount = $withdrawAnchorAccount;
        $this->withdrawMemo = $withdrawMemo;
        $this->withdrawMemoType = $withdrawMemoType;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        $json = parent::toJson();
        $json['from'] = $this->from;
        $json['to'] = $this->to;
        $json['withdraw_anchor_account'] = $this->withdrawAnchorAccount;
        $json['withdraw_memo'] = $this->withdrawMemo;
        if ($this->withdrawMemoType !== null) {
            $json['withdraw_memo_type'] = $this->withdrawMemoType;
        }

        return $json;
    }
}
