<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.


namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep24Refunds;
use DateTime;

class Sep24DepositTransactionResponse extends Sep24TransactionResponse
{
    /**
     * @var string|null $from Sent from address, perhaps BTC, IBAN, or bank account.
     */
    public ?string $from = null;

    /**
     * @var string|null $to Stellar address the deposited assets were sent to.
     */
    public ?string $to = null;

    /**
     * @var string|null $claimableBalanceId ID of the Claimable Balance used to send the asset initially requested.
     */
    public ?string $claimableBalanceId = null;

    /**
     * @var string|null $depositMemo This is the memo (if any) used to transfer the asset to the to Stellar address.
     */
    public ?string $depositMemo = null;

    /**
     * @var string|null $depositMemoType Type for the depositMemo.
     */
    public ?string $depositMemoType = null;

    /**
     * @param string $id Unique, anchor-generated id for the deposit.
     * @param string $status Processing status of deposit. (see Sep24TransactionStatus)
     * @param DateTime $startedAt Start date and time of transaction.
     * @param string|null $from Sent from address, perhaps BTC, IBAN, or bank account.
     * @param string|null $to Stellar address the deposited assets were sent to.
     * @param string|null $amountIn Amount received by anchor at start of transaction as a string with up to 7 decimals. Excludes any fees charged before the anchor received the funds.
     * @param string|null $amountOut Amount sent by anchor to user at end of transaction as a string with up to 7 decimals. Excludes amount converted to XLM to fund account and any external fees.
     * @param string|null $amountFee Amount of fee charged by anchor.
     * @param string|null $moreInfoUrl A URL that is opened by wallets after the interactive flow is complete. It can include banking information for users to start deposits, the status of the transaction, or any other information the user might need to know about the transaction.
     * @param string|null $stellarTransactionId transaction id on Stellar network of the transfer that completed the deposit.
     * @param string|null $claimableBalanceId ID of the Claimable Balance used to send the asset initially requested.
     * @param string|null $depositMemo This is the memo (if any) used to transfer the asset to the to Stellar address.
     * @param string|null $depositMemoType Type for the depositMemo.
     * @param int|null $statusEta Estimated number of seconds until a status change is expected.
     * @param bool|null $kycVerified true, if the anchor has verified the user's KYC information for this transaction.
     * @param IdentificationFormatAsset|null $amountInAsset The asset received or to be received by the Anchor. Must be present if the deposit was made using non-equivalent assets.
     * @param IdentificationFormatAsset|null $amountOutAsset The asset delivered or to be delivered to the user. Must be present if the deposit was made using non-equivalent assets.
     * @param IdentificationFormatAsset|null $amountFeeAsset The asset in which fees are calculated in. Must be present if the deposit was made using non-equivalent assets.
     * @param string|null $quoteId The ID of the quote used when creating this transaction.
     * @param DateTime|null $completedAt The date and time of transaction reaching completed or refunded status.
     * @param DateTime|null $updatedAt The date and time of transaction reaching the current status.
     * @param string|null $externalTransactionId ID of transaction on external network that either started the deposit.
     * @param string|null $message Human-readable explanation of transaction status, if needed.
     * @param bool|null $refunded True if the transaction was refunded in full. False if the transaction was partially refunded or not refunded.
     * @param Sep24Refunds|null $refunds An object describing any on or off-chain refund associated with this transaction.
     */
    public function __construct(
        string $id,
        string $status,
        DateTime $startedAt,
        ?string $from = null,
        ?string $to = null,
        ?string $amountIn = null,
        ?string $amountOut = null,
        ?string $amountFee = null,
        ?string $moreInfoUrl = null,
        ?string $stellarTransactionId = null,
        ?string $claimableBalanceId = null,
        ?string $depositMemo = null,
        ?string $depositMemoType = null,
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
            kind:'deposit',
            status:$status,
            startedAt: $startedAt,
            amountIn: $amountIn,
            amountOut: $amountOut,
            amountFee: $amountFee,
            moreInfoUrl: $moreInfoUrl,
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
        $this->claimableBalanceId = $claimableBalanceId;
        $this->depositMemo = $depositMemo;
        $this->depositMemoType = $depositMemoType;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        $json = parent::toJson();
        $json['from'] = $this->from;
        $json['to'] = $this->to;

        if ($this->depositMemo !== null) {
            $json['deposit_memo'] = $this->depositMemo;
        }

        if ($this->depositMemoType !== null) {
            $json['deposit_memo_type'] = $this->depositMemoType;
        }

        if ($this->claimableBalanceId !== null) {
            $json['claimable_balance_id'] = $this->claimableBalanceId;
        }

        return $json;
    }
}
