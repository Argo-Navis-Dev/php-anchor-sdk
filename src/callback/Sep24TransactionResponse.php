<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep24Refunds;
use DateTime;
use DateTimeInterface;

abstract class Sep24TransactionResponse
{
    /**
     * @var string $id Unique, anchor-generated id for the deposit/withdrawal.
     */
    public string $id;

    /**
     * @var string $kind 'deposit' or 'withdrawal'.
     */
    public string $kind;

    /**
     * @var string $status Processing status of deposit/withdrawal. (see Sep24TransactionStatus)
     */
    public string $status;

    /**
     * @var int|null $statusEta Estimated number of seconds until a status change is expected.
     */
    public ?int $statusEta = null;

    /**
     * @var bool|null $kycVerifiedTrue true, if the anchor has verified the user's KYC information for this transaction.
     */
    public ?bool $kycVerified = null;

    /**
     * @var string $moreInfoUrl A URL that is opened by wallets after the interactive flow is complete. It can include banking information for users to start deposits, the status of the transaction, or any other information the user might need to know about the transaction.
     */
    public string $moreInfoUrl;

    /**
     * @var string $amountIn Amount received by anchor at start of transaction as a string with up to 7 decimals. Excludes any fees charged before the anchor received the funds.
     */
    public string $amountIn;

    /**
     * @var IdentificationFormatAsset|null $amountInAsset The asset received or to be received by the Anchor. Must be present if the deposit/withdraw was made using non-equivalent assets.
     */
    public ?IdentificationFormatAsset $amountInAsset = null;

    /**
     * @var string $amountOut Amount sent by anchor to user at end of transaction as a string with up to 7 decimals. Excludes amount converted to XLM to fund account and any external fees.
     */
    public string $amountOut;

    /**
     * @var IdentificationFormatAsset|null $amountOutAsset The asset delivered or to be delivered to the user. Must be present if the deposit/withdraw was made using non-equivalent assets.
     */
    public ?IdentificationFormatAsset $amountOutAsset = null;

    /**
     * @var string $amountFee Amount of fee charged by anchor.
     */
    public string $amountFee;

    /**
     * @var IdentificationFormatAsset|null $amountFeeAsset The asset in which fees are calculated in. Must be present if the deposit/withdraw was made using non-equivalent assets.
     */
    public ?IdentificationFormatAsset $amountFeeAsset = null;

    /**
     * @var string|null $quoteId The ID of the quote used when creating this transaction.
     */
    public ?string $quoteId = null;

    /**
     * @var DateTime $startedAt Start date and time of transaction.
     */
    public DateTime $startedAt;

    /**
     * @var DateTime|null $completedAt The date and time of transaction reaching completed or refunded status.
     */
    public ?DateTime $completedAt = null;

    /**
     * @var DateTime|null $updatedAt The date and time of transaction reaching the current status.
     */
    public ?DateTime $updatedAt = null;

    /**
     * @var string $stellarTransactionId transaction id on Stellar network of the transfer that either completed the deposit or started the withdrawal.
     */
    public string $stellarTransactionId;

    /**
     * @var string|null $externalTransactionId ID of transaction on external network that either started the deposit or completed the withdrawal.
     */
    public ?string $externalTransactionId = null;

    /**
     * @var string|null $message Human-readable explanation of transaction status, if needed.
     */
    public ?string $message = null;

    /**
     * @var bool|null $refunded True if the transaction was refunded in full. False if the transaction was partially refunded or not refunded.
     */
    public ?bool $refunded = null;

    /**
     * @var Sep24Refunds|null $refunds An object describing any on or off-chain refund associated with this transaction.
     */
    public ?Sep24Refunds $refunds = null;

    /**
     * @param string $id Unique, anchor-generated id for the deposit/withdrawal.
     * @param string $kind 'deposit' or 'withdrawal'.
     * @param string $status Processing status of deposit/withdrawal. (see Sep24TransactionStatus)
     * @param string $moreInfoUrl A URL that is opened by wallets after the interactive flow is complete. It can include banking information for users to start deposits, the status of the transaction, or any other information the user might need to know about the transaction.
     * @param string $amountIn Amount received by anchor at start of transaction as a string with up to 7 decimals. Excludes any fees charged before the anchor received the funds.
     * @param string $amountOut Amount sent by anchor to user at end of transaction as a string with up to 7 decimals. Excludes amount converted to XLM to fund account and any external fees.
     * @param string $amountFee Amount of fee charged by anchor.
     * @param DateTime $startedAt Start date and time of transaction.
     * @param string $stellarTransactionId transaction id on Stellar network of the transfer that either completed the deposit or started the withdrawal.
     * @param int|null $statusEta Estimated number of seconds until a status change is expected.
     * @param bool|null $kycVerified true, if the anchor has verified the user's KYC information for this transaction.
     * @param IdentificationFormatAsset|null $amountInAsset The asset received or to be received by the Anchor. Must be present if the deposit/withdraw was made using non-equivalent assets.
     * @param IdentificationFormatAsset|null $amountOutAsset The asset delivered or to be delivered to the user. Must be present if the deposit/withdraw was made using non-equivalent assets.
     * @param IdentificationFormatAsset|null $amountFeeAsset The asset in which fees are calculated in. Must be present if the deposit/withdraw was made using non-equivalent assets.
     * @param string|null $quoteId The ID of the quote used when creating this transaction.
     * @param DateTime|null $completedAt The date and time of transaction reaching completed or refunded status.
     * @param DateTime|null $updatedAt The date and time of transaction reaching the current status.
     * @param string|null $externalTransactionId ID of transaction on external network that either started the deposit or completed the withdrawal.
     * @param string|null $message Human-readable explanation of transaction status, if needed.
     * @param bool|null $refunded True if the transaction was refunded in full. False if the transaction was partially refunded or not refunded.
     * @param Sep24Refunds|null $refunds An object describing any on or off-chain refund associated with this transaction.
     */
    public function __construct(
        string $id,
        string $kind,
        string $status,
        string $moreInfoUrl,
        string $amountIn,
        string $amountOut,
        string $amountFee,
        DateTime $startedAt,
        string $stellarTransactionId,
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
        $this->id = $id;
        $this->kind = $kind;
        $this->status = $status;
        $this->moreInfoUrl = $moreInfoUrl;
        $this->amountIn = $amountIn;
        $this->amountOut = $amountOut;
        $this->amountFee = $amountFee;
        $this->startedAt = $startedAt;
        $this->stellarTransactionId = $stellarTransactionId;

        $this->statusEta = $statusEta;
        $this->kycVerified = $kycVerified;
        $this->amountInAsset = $amountInAsset;
        $this->amountOutAsset = $amountOutAsset;
        $this->amountFeeAsset = $amountFeeAsset;
        $this->quoteId = $quoteId;
        $this->completedAt = $completedAt;
        $this->updatedAt = $updatedAt;
        $this->externalTransactionId = $externalTransactionId;
        $this->message = $message;
        $this->refunded = $refunded;
        $this->refunds = $refunds;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $json
         */
        $json = ['id' => $this->id,
            'kind' => $this->kind,
            'status' => $this->status,
        ];

        if ($this->statusEta !== null) {
            $json['status_eta'] = $this->statusEta;
        }

        if ($this->kycVerified !== null) {
            $json['kyc_verified'] = $this->kycVerified;
        }

        $json['more_info_url'] = $this->moreInfoUrl;
        $json['amount_in'] = $this->amountIn;

        if ($this->amountInAsset !== null) {
            $json['amount_in_asset'] = $this->amountInAsset->getStringRepresentation();
        }

        $json['amount_out'] = $this->amountOut;

        if ($this->amountOutAsset !== null) {
            $json['amount_out_asset'] = $this->amountOutAsset->getStringRepresentation();
        }

        $json['amount_fee'] = $this->amountFee;

        if ($this->amountFeeAsset !== null) {
            $json['amount_fee_asset'] = $this->amountFeeAsset->getStringRepresentation();
        }

        if ($this->quoteId !== null) {
            $json['quote_id'] = $this->quoteId;
        }

        $json['started_at'] = $this->startedAt->format(
            DateTimeInterface::ATOM,
        );

        if ($this->completedAt !== null) {
            $json['completed_at'] = $this->completedAt->format(
                DateTimeInterface::ATOM,
            );
        }

        if ($this->updatedAt !== null) {
            $json['updated_at'] = $this->updatedAt->format(
                DateTimeInterface::ATOM,
            );
        }

        $json['stellar_transaction_id'] = $this->stellarTransactionId;

        if ($this->externalTransactionId !== null) {
            $json['external_transaction_id'] = $this->externalTransactionId;
        }

        if ($this->message !== null) {
            $json['message'] = $this->message;
        }

        if ($this->refunded !== null) {
            $json['refunded'] = $this->refunded;
        }

        if ($this->refunds !== null) {
            $json['refunds'] = $this->refunds->toJson();
        }

        return $json;
    }
}
