<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\InstructionsField;
use ArgoNavis\PhpAnchorSdk\shared\Sep06InfoField;
use ArgoNavis\PhpAnchorSdk\shared\TransactionFeeInfo;
use ArgoNavis\PhpAnchorSdk\shared\TransactionRefunds;
use DateTime;
use DateTimeInterface;

class Sep06TransactionResponse
{
    /**
     * @var string $id Unique, anchor-generated id for the deposit/withdrawal.
     */
    public string $id;

    /**
     * @var string $kind deposit, deposit-exchange, withdrawal or withdrawal-exchange.
     */
    public string $kind;

    /**
     * @var string $status Processing status of deposit/withdrawal. (see Sep06TransactionStatus)
     */
    public string $status;

    /**
     * @var int|null $statusEta Estimated number of seconds until a status change is expected.
     */
    public ?int $statusEta = null;

    /**
     * @var string|null $moreInfoUrl A URL the user can visit if they want more information about their account / status.
     */
    public ?string $moreInfoUrl = null;

    /**
     * @var string|null $amountIn Amount received by anchor at start of transaction as a string with up to 7 decimals.
     * Excludes any fees charged before the anchor received the funds. Should be equals to quote.sell_asset if a quote_id was used.
     */
    public ?string $amountIn = null;

    /**
     * @var IdentificationFormatAsset|null $amountInAsset The asset received or to be received by the Anchor.
     * Must be present if the deposit/withdraw was made using quotes.
     */
    public ?IdentificationFormatAsset $amountInAsset = null;

    /**
     * @var string|null $amountOut Amount sent by anchor to user at end of transaction as a string with up to 7 decimals.
     * Excludes amount converted to XLM to fund account and any external fees.
     * Should be equals to quote.buy_asset if a quote_id was used.
     */
    public ?string $amountOut = null;

    /**
     * @var IdentificationFormatAsset|null $amountOutAsset The asset delivered or to be delivered to the user.
     * Must be present if the deposit/withdraw was made using quotes.
     */
    public ?IdentificationFormatAsset $amountOutAsset = null;


    /**
     * @var TransactionFeeInfo|null $feeDetails Description of fee charged by the anchor.
     * If quote_id is present, it should match the referenced quote's fee object.
     */
    public ?TransactionFeeInfo $feeDetails = null;

    /**
     * @var string|null $quoteId The ID of the quote used when creating this transaction.
     */
    public ?string $quoteId = null;

    /**
     * @var string|null $from Sent from address (perhaps BTC, IBAN, or bank account in the case of a deposit,
     * Stellar address in the case of a withdrawal).
     */
    public ?string $from = null;

    /**
     * @var string|null $to Sent to address (perhaps BTC, IBAN, or bank account in the case of a withdrawal,
     * Stellar address in the case of a deposit).
     */
    public ?string $to = null;

    /**
     * @var string|null $externalExtra Extra information for the external account involved. It could be a bank
     * routing number, BIC, or store number for example.
     */
    public ?string $externalExtra = null;


    /**
     * @var string|null $externalExtraText Text version of $externalExtra. This is the name of the bank or store.
     */
    public ?string $externalExtraText = null;

    /**
     * @var string|null $depositMemo If this is a deposit, this is the memo (if any) used to transfer the
     * asset to the 'to' Stellar address.
     */
    public ?string $depositMemo = null;

    /**
     * @var string|null $depositMemoType Type for the $depositMemo.
     */
    public ?string $depositMemoType = null;

    /**
     * @var string|null $withdrawAnchorAccount if this is a withdrawal, this is the anchor's Stellar account that the
     * user transferred (or will transfer) their issued asset to.
     */
    public ?string $withdrawAnchorAccount = null;

    /**
     * @var string|null $withdrawMemo Memo used when the user transferred to $withdrawAnchorAccount.
     */
    public ?string $withdrawMemo = null;

    /**
     * @var string|null $withdrawMemoType Memo type for $withdrawMemo.
     */
    public ?string $withdrawMemoType = null;

    /**
     * @var DateTime|null $startedAt Start date and time of transaction.
     */
    public ?DateTime $startedAt = null;

    /**
     * @var DateTime|null $completedAt Completion date and time of transaction.
     */
    public ?DateTime $completedAt = null;

    /**
     * @var DateTime|null $updatedAt The date and time of transaction reaching the current status.
     */
    public ?DateTime $updatedAt = null;

    /**
     * @var string|null $stellarTransactionId transaction id on Stellar network of the transfer that either completed the deposit or started the withdrawal.
     */
    public ?string $stellarTransactionId = null;

    /**
     * @var string|null $externalTransactionId ID of transaction on external network that either started the deposit or completed the withdrawal.
     */
    public ?string $externalTransactionId = null;

    /**
     * @var string|null $message Human-readable explanation of transaction status, if needed.
     */
    public ?string $message = null;

    /**
     * @var TransactionRefunds|null $refunds An object describing any on or off-chain refund associated with this transaction.
     */
    public ?TransactionRefunds $refunds = null;

    /**
     * @var string|null $requiredInfoMessage A human-readable message indicating any errors that require updated information from the user.
     */
    public ?string $requiredInfoMessage = null;


    /**
     * @var array<Sep06InfoField>|null $requiredInfoUpdates A set of fields that require update from the user described in the
     * same format as /info. This field is only relevant when status is pending_transaction_info_update.
     */
    public ?array $requiredInfoUpdates = null;

    /**
     * @var array<InstructionsField>|null $instructions (optional) an array containing the SEP-9 financial account
     * fields that describe how to complete the off-chain deposit.
     */
    public ?array $instructions = null;

    /**
     * @var string|null $claimableBalanceId ID of the Claimable Balance used to send the asset initially requested.
     * Only relevant for deposit transactions.
     */
    public ?string $claimableBalanceId = null;

    /**
     * @param string $id Unique, anchor-generated id for the deposit/withdrawal.
     * @param string $kind deposit, deposit-exchange, withdrawal or withdrawal-exchange.
     * @param string $status Processing status of deposit/withdrawal.
     * If quote_id is present, it should match the referenced quote's fee object.
     * @param int|null $statusEta Estimated number of seconds until a status change is expected.
     * @param string|null $moreInfoUrl A URL the user can visit if they want more information about their account / status.
     * @param string|null $amountIn Amount received by anchor at start of transaction as a string with up to 7 decimals.
     * Excludes any fees charged before the anchor received the funds. Should be equals to quote.sell_asset if a quote_id was used.
     * @param IdentificationFormatAsset|null $amountInAsset The asset received or to be received by the Anchor.
     * Must be present if the deposit/withdraw was made using quotes.
     * @param string|null $amountOut Amount sent by anchor to user at end of transaction as a string with up to 7 decimals.
     * Excludes amount converted to XLM to fund account and any external fees.
     * Should be equals to quote.buy_asset if a quote_id was used.
     * @param IdentificationFormatAsset|null $amountOutAsset The asset delivered or to be delivered to the user.
     * Must be present if the deposit/withdraw was made using quotes.
     * @param TransactionFeeInfo|null $feeDetails Description of fee charged by the anchor.
     * @param string|null $quoteId The ID of the quote used to create this transaction.
     * Should be present if a quote_id was included in the request.
     * @param string|null $from Sent from address - perhaps BTC, IBAN, or bank account in the case of a deposit,
     * Stellar address in the case of a withdrawal.
     * @param string|null $to Sent to address - perhaps BTC, IBAN, or bank account in the case of a withdrawal,
     * Stellar address in the case of a deposit.
     * @param string|null $externalExtra Extra information for the external account involved. It could be a bank routing
     * number, BIC, or store number for example.
     * @param string|null $externalExtraText Text version of $externalExtra. This is the name of the bank or store.
     * @param string|null $depositMemo If this is a deposit, this is the memo (if any) used to transfer the asset to the
     * 'to' Stellar address.
     * @param string|null $depositMemoType Type for the $depositMemo.
     * @param string|null $withdrawAnchorAccount If this is a withdrawal, this is the anchor's Stellar account that
     * the user transferred (or will transfer) their issued asset to.
     * @param string|null $withdrawMemo Memo used when the user transferred to $withdrawAnchorAccount.
     * @param string|null $withdrawMemoType Memo type for $withdrawMemo.
     * @param DateTime|null $startedAt Start date and time of transaction.
     * @param DateTime|null $completedAt The date and time of transaction reaching the current status.
     * @param DateTime|null $updatedAt Completion date and time of transaction.
     * @param string|null $stellarTransactionId transaction_id on Stellar network of the transfer that either
     * completed the deposit or started the withdrawal.
     * @param string|null $externalTransactionId ID of transaction on external network that either started the
     * deposit or completed the withdrawal.
     * @param string|null $message Human-readable explanation of transaction status, if needed.
     * @param TransactionRefunds|null $refunds An object describing any on or off-chain refund associated with
     * this transaction.
     * @param string|null $requiredInfoMessage A human-readable message indicating any errors that require
     * updated information from the user.
     * @param array<Sep06InfoField>|null $requiredInfoUpdates A set of fields that require update from the user.
     * This field is only relevant when status is pending_transaction_info_update.
     * @param array<InstructionsField>|null $instructions JSON object containing the SEP-9 financial account fields that
     * describe how to complete the off-chain deposit in the same format as the /deposit response.
     * This field should be present if the instructions were provided in the /deposit response or if it
     * could not have been previously provided synchronously. This field should only be present once the status
     * becomes pending_user_transfer_start, not while the transaction has any statuses that precede it such as
     * incomplete, pending_anchor, or pending_customer_info_update.
     * @param string|null $claimableBalanceId ID of the Claimable Balance used to send the asset
     * initially requested. Only relevant for deposit transactions.
     */
    public function __construct(
        string $id,
        string $kind,
        string $status,
        ?int $statusEta = null,
        ?string $moreInfoUrl = null,
        ?string $amountIn = null,
        ?IdentificationFormatAsset $amountInAsset = null,
        ?string $amountOut = null,
        ?IdentificationFormatAsset $amountOutAsset = null,
        ?TransactionFeeInfo $feeDetails = null,
        ?string $quoteId = null,
        ?string $from = null,
        ?string $to = null,
        ?string $externalExtra = null,
        ?string $externalExtraText = null,
        ?string $depositMemo = null,
        ?string $depositMemoType = null,
        ?string $withdrawAnchorAccount = null,
        ?string $withdrawMemo = null,
        ?string $withdrawMemoType = null,
        ?DateTime $startedAt = null,
        ?DateTime $completedAt = null,
        ?DateTime $updatedAt = null,
        ?string $stellarTransactionId = null,
        ?string $externalTransactionId = null,
        ?string $message = null,
        ?TransactionRefunds $refunds = null,
        ?string $requiredInfoMessage = null,
        ?array $requiredInfoUpdates = null,
        ?array $instructions = null,
        ?string $claimableBalanceId = null,
    ) {
        $this->id = $id;
        $this->kind = $kind;
        $this->status = $status;
        $this->feeDetails = $feeDetails;
        $this->statusEta = $statusEta;
        $this->moreInfoUrl = $moreInfoUrl;
        $this->amountIn = $amountIn;
        $this->amountInAsset = $amountInAsset;
        $this->amountOut = $amountOut;
        $this->amountOutAsset = $amountOutAsset;
        $this->quoteId = $quoteId;
        $this->from = $from;
        $this->to = $to;
        $this->externalExtra = $externalExtra;
        $this->externalExtraText = $externalExtraText;
        $this->depositMemo = $depositMemo;
        $this->depositMemoType = $depositMemoType;
        $this->withdrawAnchorAccount = $withdrawAnchorAccount;
        $this->withdrawMemo = $withdrawMemo;
        $this->withdrawMemoType = $withdrawMemoType;
        $this->startedAt = $startedAt;
        $this->completedAt = $completedAt;
        $this->updatedAt = $updatedAt;
        $this->stellarTransactionId = $stellarTransactionId;
        $this->externalTransactionId = $externalTransactionId;
        $this->message = $message;
        $this->refunds = $refunds;
        $this->requiredInfoMessage = $requiredInfoMessage;
        $this->requiredInfoUpdates = $requiredInfoUpdates;
        $this->instructions = $instructions;
        $this->claimableBalanceId = $claimableBalanceId;
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

        if ($this->moreInfoUrl !== null) {
            $json['more_info_url'] = $this->moreInfoUrl;
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

        if ($this->feeDetails !== null) {
            $json['fee_details'] = $this->feeDetails->toJson();
        }

        if ($this->quoteId !== null) {
            $json['quote_id'] = $this->quoteId;
        }

        if ($this->from !== null) {
            $json['from'] = $this->from;
        }

        if ($this->to !== null) {
            $json['to'] = $this->to;
        }

        if ($this->externalExtra !== null) {
            $json['external_extra'] = $this->externalExtra;
        }

        if ($this->externalExtraText !== null) {
            $json['external_extra_text'] = $this->externalExtraText;
        }

        if ($this->depositMemo !== null) {
            $json['deposit_memo'] = $this->depositMemo;
        }

        if ($this->depositMemoType !== null) {
            $json['deposit_memo_type'] = $this->depositMemoType;
        }

        if ($this->withdrawAnchorAccount !== null) {
            $json['withdraw_anchor_account'] = $this->withdrawAnchorAccount;
        }

        if ($this->withdrawMemo !== null) {
            $json['withdraw_memo'] = $this->withdrawMemo;
        }

        if ($this->withdrawMemoType !== null) {
            $json['withdraw_memo_type'] = $this->withdrawMemoType;
        }

        if ($this->startedAt !== null) {
            $json['started_at'] = $this->startedAt->format(
                DateTimeInterface::ATOM,
            );
        }

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

        if ($this->stellarTransactionId !== null) {
            $json['stellar_transaction_id'] = $this->stellarTransactionId;
        }

        if ($this->externalTransactionId !== null) {
            $json['external_transaction_id'] = $this->externalTransactionId;
        }

        if ($this->message !== null) {
            $json['message'] = $this->message;
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

        if ($this->instructions !== null) {
            /**
             * @var array<array-key, mixed> $fields
             */
            $fields = [];
            foreach ($this->instructions as $field) {
                $fields += [$field->name => ['value' => $field->value, 'description' => $field->description]];
            }
            $json['instructions'] = $fields;
        }

        if ($this->claimableBalanceId !== null) {
            $json['claimable_balance_id'] = $this->claimableBalanceId;
        }

        // $str = json_encode($json);
        // print($str .PHP_EOL);
        return $json;
    }
}
