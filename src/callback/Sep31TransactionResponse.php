<?php

declare(strict_types=1);

// Copyright 2024 The Stellar PHP SDK Authors. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep31InfoField;
use ArgoNavis\PhpAnchorSdk\shared\TransactionFeeInfo;
use ArgoNavis\PhpAnchorSdk\shared\TransactionRefunds;
use DateTime;
use DateTimeInterface;

class Sep31TransactionResponse
{
    /**
     * @var string $id The ID returned from the POST /transactions request that created this transaction record.
     */
    public string $id;

    /**
     * @var string $status The status of the transaction. See also Sep31TransactionStatus
     */
    public string $status;

    /**
     * @var TransactionFeeInfo $feeDetails Description of fee charged by the anchor.
     */
    public TransactionFeeInfo $feeDetails;

    /**
     * @var int|null $statusEta (optional) The estimated number of seconds until a status change is expected.
     */
    public ?int $statusEta = null;

    /**
     * @var string|null $statusMessage (optional) A human-readable message describing the status of the transaction.
     */
    public ?string $statusMessage = null;

    /**
     * @var string|null $amountIn (optional) The amount of the Stellar asset received or to be received by the
     * Receiving Anchor. Excludes any fees charged after Receiving Anchor receives the funds. If a quote_id was
     * used, the amount_in should be equals to both: (i) the amount value used in the POST /transactions request;
     * and (ii) the quote's sell_amount.
     */
    public ?string $amountIn = null;

    /**
     * @var IdentificationFormatAsset|null $amountInAsset (optional) The asset received or to be received by the Receiving Anchor.
     * Must be present if quote_id or destination_asset was included in the POST /transactions request.
     */
    public ?IdentificationFormatAsset $amountInAsset = null;

    /**
     * @var string|null $amountOut (optional) The amount sent or to be sent by the Receiving Anchor to the
     * Receiving Client. When using a destination_asset in the POST /transactions request, it's expected that this
     * value is only populated after the Receiving Anchor receives the incoming payment. Should be equals to
     * quote.buy_amount if a quote_id was used.
     */
    public ?string $amountOut = null;

    /**
     * @var IdentificationFormatAsset|null $amountOutAsset (optional) The asset delivered to the Receiving Client.
     * Must be present if quote_id or destination_asset was included in the POST /transactions request.
     */
    public ?IdentificationFormatAsset $amountOutAsset = null;

    /**
     * @var string|null $quoteId (optional) The ID of the quote used to create this transaction.
     * Should be present if a quote_id was included in the POST /transactions request. Clients should be aware
     * though that the quote_id may not be present in older implementations.
     */
    public ?string $quoteId = null;

    /**
     * @var string|null $stellarAccountId (optional) The Receiving Anchor's Stellar account that the Sending Anchor
     * will be making the payment to.
     */
    public ?string $stellarAccountId = null;

    /**
     * @var string|null $stellarMemoType (optional) The type of memo to attach to the Stellar payment: text, hash, or id.
     */
    public ?string $stellarMemoType = null;

    /**
     * @var string|null $stellarMemo (optional) The memo to attach to the Stellar payment.
     */
    public ?string $stellarMemo = null;

    /**
     * @var DateTime|null $startedAt (optional) Start date and time of transaction.
     */
    public ?DateTime $startedAt = null;

    /**
     * @var DateTime|null $updatedAt (optional) The date and time of transaction reaching the current status.
     */
    public ?DateTime $updatedAt = null;

    /**
     * @var DateTime|null $completedAt (optional) Completion date and time of transaction.
     */
    public ?DateTime $completedAt = null;

    /**
     * @var string|null $stellarTransactionId (optional) The transaction_id on Stellar network of the transfer
     * that initiated the payment.
     */
    public ?string $stellarTransactionId = null;

    /**
     * @var string|null $externalTransactionId (optional) The ID of transaction on external network that completes
     * the payment into the receivers account.
     */
    public ?string $externalTransactionId = null;

    /**
     * @var TransactionRefunds|null $refunds (optional) An object describing any on-chain refund associated with this transaction.
     */
    public ?TransactionRefunds $refunds = null;

    /**
     * @var string|null $requiredInfoMessage (optional) A human-readable message indicating any errors that
     * require updated information from the sender.
     */
    public ?string $requiredInfoMessage = null;
    /**
     * @var array<Sep31InfoField>|null $requiredInfoUpdates A set of fields that require update values from
     * the Sending Anchor.
     */
    public ?array $requiredInfoUpdates = null;

    /**
     * @param string $id The ID returned from the POST /transactions request that created this transaction record.
     * @param string $status The status of the transaction. See also Sep31TransactionStatus
     * @param TransactionFeeInfo $feeDetails TransactionFeeInfo $feeDetails Description of fee charged by the anchor.
     * @param int|null $statusEta (optional) The estimated number of seconds until a status change is expected.
     * @param string|null $statusMessage (optional) A human-readable message describing the status of the transaction.
     * @param string|null $amountIn (optional) The amount of the Stellar asset received or to be received by the
     *  Receiving Anchor. Excludes any fees charged after Receiving Anchor receives the funds. If a quote_id was
     *  used, the amount_in should be equals to both: (i) the amount value used in the POST /transactions request;
     *  and (ii) the quote's sell_amount.
     * @param IdentificationFormatAsset|null $amountInAsset (optional) The asset received or to be received by the Receiving Anchor.
     *  Must be present if quote_id or destination_asset was included in the POST /transactions request.
     * @param string|null $amountOut (optional) The amount sent or to be sent by the Receiving Anchor to the
     *  Receiving Client. When using a destination_asset in the POST /transactions request, it's expected that this
     *  value is only populated after the Receiving Anchor receives the incoming payment. Should be equals to
     *  quote.buy_amount if a quote_id was used.
     * @param IdentificationFormatAsset|null $amountOutAsset (optional) The asset delivered to the Receiving Client.
     *  Must be present if quote_id or destination_asset was included in the POST /transactions request.
     * @param string|null $quoteId (optional) The ID of the quote used to create this transaction.
     *  Should be present if a quote_id was included in the POST /transactions request. Clients should be aware
     *  though that the quote_id may not be present in older implementations.
     * @param string|null $stellarAccountId (optional) The Receiving Anchor's Stellar account that the Sending Anchor
     *  will be making the payment to.
     * @param string|null $stellarMemoType (optional) The type of memo to attach to the Stellar payment: text, hash, or id.
     * @param string|null $stellarMemo (optional) The memo to attach to the Stellar payment.
     * @param DateTime|null $startedAt (optional) Start date and time of transaction.
     * @param DateTime|null $updatedAt (optional) The date and time of transaction reaching the current status.
     * @param DateTime|null $completedAt (optional) Completion date and time of transaction.
     * @param string|null $stellarTransactionId (optional) The transaction_id on Stellar network of the transfer
     *  that initiated the payment.
     * @param string|null $externalTransactionId (optional) The ID of transaction on external network that completes
     *  the payment into the receivers account.
     * @param TransactionRefunds|null $refunds (optional) An object describing any on-chain refund associated with this transaction.
     * @param string|null $requiredInfoMessage (optional) A human-readable message indicating any errors that
     *  require updated information from the sender.
     * @param array<Sep31InfoField>|null $requiredInfoUpdates (optional) A set of fields that require update values from
     *  the Sending Anchor.
     */
    public function __construct(
        string $id,
        string $status,
        TransactionFeeInfo $feeDetails,
        ?int $statusEta = null,
        ?string $statusMessage = null,
        ?string $amountIn = null,
        ?IdentificationFormatAsset $amountInAsset = null,
        ?string $amountOut = null,
        ?IdentificationFormatAsset $amountOutAsset = null,
        ?string $quoteId = null,
        ?string $stellarAccountId = null,
        ?string $stellarMemoType = null,
        ?string $stellarMemo = null,
        ?DateTime $startedAt = null,
        ?DateTime $updatedAt = null,
        ?DateTime $completedAt = null,
        ?string $stellarTransactionId = null,
        ?string $externalTransactionId = null,
        ?TransactionRefunds $refunds = null,
        ?string $requiredInfoMessage = null,
        ?array $requiredInfoUpdates = null,
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->feeDetails = $feeDetails;
        $this->statusEta = $statusEta;
        $this->statusMessage = $statusMessage;
        $this->amountIn = $amountIn;
        $this->amountInAsset = $amountInAsset;
        $this->amountOut = $amountOut;
        $this->amountOutAsset = $amountOutAsset;
        $this->quoteId = $quoteId;
        $this->stellarAccountId = $stellarAccountId;
        $this->stellarMemoType = $stellarMemoType;
        $this->stellarMemo = $stellarMemo;
        $this->startedAt = $startedAt;
        $this->updatedAt = $updatedAt;
        $this->completedAt = $completedAt;
        $this->stellarTransactionId = $stellarTransactionId;
        $this->externalTransactionId = $externalTransactionId;
        $this->refunds = $refunds;
        $this->requiredInfoMessage = $requiredInfoMessage;
        $this->requiredInfoUpdates = $requiredInfoUpdates;
    }

    /**
     * Convert the object to a JSON representation.
     *
     * @return array<string, mixed> The JSON representation of the object.
     */
    public function toJson(): array
    {
        $json = [];
        $json['id'] = $this->id;
        $json['status'] = $this->status;
        if ($this->statusEta !== null) {
            $json['status_eta'] = $this->statusEta;
        }
        if ($this->statusMessage !== null) {
            $json['status_message'] = $this->statusMessage;
        }
        if ($this->amountIn !== null) {
            $json['amount_in'] = $this->amountIn;
        }
        if ($this->amountInAsset !== null) {
            $json['amount_in_asset'] = $this->amountInAsset->getStringRepresentation();
        }
        if ($this->amountOut !== null) {
            $json['amount_out'] = $this->amountOut;
        }
        if ($this->amountOutAsset !== null) {
            $json['amount_out_asset'] = $this->amountOutAsset->getStringRepresentation();
        }

        $json['fee_details'] = $this->feeDetails->toJson();

        if ($this->quoteId !== null) {
            $json['quote_id'] = $this->quoteId;
        }
        if ($this->stellarAccountId !== null) {
            $json['stellar_account_id'] = $this->stellarAccountId;
        }
        if ($this->stellarMemoType !== null) {
            $json['stellar_memo_type'] = $this->stellarMemoType;
        }
        if ($this->stellarMemo !== null) {
            $json['stellar_memo'] = $this->stellarMemo;
        }
        if ($this->startedAt !== null) {
            $json['started_at'] = $this->startedAt->format(DateTimeInterface::ATOM);
        }
        if ($this->updatedAt !== null) {
            $json['updated_at'] = $this->updatedAt->format(DateTimeInterface::ATOM);
        }
        if ($this->completedAt !== null) {
            $json['completed_at'] = $this->completedAt->format(DateTimeInterface::ATOM);
        }
        if ($this->stellarTransactionId !== null) {
            $json['stellar_transaction_id'] = $this->stellarTransactionId;
        }
        if ($this->externalTransactionId !== null) {
            $json['external_transaction_id'] = $this->externalTransactionId;
        }
        if ($this->refunds !== null) {
            $json['refunds'] = $this->refunds->toJson();
        }
        if ($this->requiredInfoMessage !== null) {
            $json['required_info_message'] = $this->requiredInfoMessage;
        }

        if ($this->requiredInfoUpdates !== null) {
            /**
             * @var array<array-key, mixed> $data
             */
            $data = [];
            foreach ($this->requiredInfoUpdates as $field) {
                $data += $field->toJson();
            }
            $json['required_info_updates'] = ['transaction' => $data];
        }

        return $json;
    }
}
