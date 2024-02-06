<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use Psr\Http\Message\UploadedFileInterface;
use Soneso\StellarSDK\Memo;

/**
 * Prepared SEP-24 withdraw request data.
 * See also <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0024.md#withdraw-2">SEP-024 withdraw</a>
 */
class InteractiveWithdrawRequest
{
    /**
     * @var string $account The Stellar account id or muxed account id the client will use as the source of the withdrawal payment to the anchor. Defaults to the account authenticated via SEP-10 if not specified by the client.
     */
    public string $account;

    /**
     * @var bool $claimableBalanceSupported True if the client supports receiving deposit transactions as a claimable balance, false otherwise.
     */
    public bool $claimableBalanceSupported;

    /**
     * @var IdentificationFormatAsset $asset Asset the user wants to withdraw.
     */
    public IdentificationFormatAsset $asset;

    /**
     * @var Sep10Jwt $jwtToken The jwt token from the SEP-10 authentication. Contains data that identifies the user and client.
     */
    public Sep10Jwt $jwtToken;

    /**
     * @var IdentificationFormatAsset|null $destinationAsset The asset user wants to receive. It's an off-chain or fiat asset. If this is not provided, it should be collected in the interactive flow.
     */
    public ?IdentificationFormatAsset $destinationAsset = null;

    /**
     * @var float|null $amount Amount of asset requested to withdraw. If this is not provided it should be collected in the interactive flow.
     */
    public ?float $amount = null;

    /**
     * @var string|null $quoteId The id returned from a SEP-38 POST /quote response.
     */
    public ?string $quoteId = null;

    /**
     * @var Memo |null $memo (deprecated) This field was originally intended to differentiate users of the same Stellar account. However, the anchor should use the sub value included in the decoded SEP-10 JWT instead.
     */
    public ?Memo $memo = null;

    /**
     * @var string|null $walletName (deprecated) In communications / pages about the withdrawal, anchor should display the wallet name to the user to explain where funds are coming from. However, anchors should use client_domain (for non-custodial) and sub value of JWT (for custodial) to determine wallet information.
     */
    public ?string $walletName = null;

    /**
     * @var string|null (deprecated) Anchor can show this to the user when referencing the wallet involved in the withdrawal (ex. in the anchor's transaction history). However, anchors should use client_domain (for non-custodial) and sub value of JWT (for custodial) to determine wallet information.
     */
    public ?string $walletUrl = null;

    /**
     * @var string|null $lang Language code specified using RFC 4646.
     */
    public ?string $lang = null;

    /**
     * @var Memo|null $refundMemo The memo the anchor must use when sending refund payments back to the user. If not specified, the anchor should use the same memo used by the user to send the original payment.
     */
    public ?Memo $refundMemo = null;

    /**
     * @var string|null Id of an off-chain account (managed by the anchor) associated with this user's Stellar account (identified by the JWT's sub field). As submitted by the client, not verified. If the anchor supports SEP-12, the customer_id field should match the SEP-12 customer's id. customerId should be passed only when the off-chain id is known to the client, but the relationship between this id and the user's Stellar account is not known to the Anchor.
     */
    public ?string $customerId = null;

    /**
     * @var array<array-key, mixed>|null The client can also transmit one or more of the fields listed in
     *  <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>
     */
    public ?array $kycFields = null;

    /**
     * @var array<array-key, UploadedFileInterface>|null Uploaded files by the client. Listed in
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>
     */
    public ?array $kycUploadedFiles = null;

    /**
     * @param string $account The Stellar account id or muxed account id the client will use as the source of the withdrawal payment to the anchor. Defaults to the account authenticated via SEP-10 if not specified by the client.
     * @param bool $claimableBalanceSupported True if the client supports receiving deposit transactions as a claimable balance, false otherwise.
     * @param IdentificationFormatAsset $asset Asset the user wants to withdraw.
     * @param Sep10Jwt $jwtToken The jwt token from the SEP-10 authentication. Contains data that identifies the user and client.
     * @param IdentificationFormatAsset|null $destinationAsset The asset user wants to receive. It's an off-chain or fiat asset. If this is not provided, it should be collected in the interactive flow.
     * @param float|null $amount Amount of asset requested to withdraw. If this is not provided it should be collected in the interactive flow.
     * @param string|null $quoteId The id returned from a SEP-38 POST /quote response.
     * @param Memo|null $memo (deprecated) This field was originally intended to differentiate users of the same Stellar account. However, the anchor should use the sub value included in the decoded SEP-10 JWT instead.
     * @param string|null $walletName (deprecated) In communications / pages about the withdrawal, anchor should display the wallet name to the user to explain where funds are coming from. However, anchors should use client_domain (for non-custodial) and sub value of JWT (for custodial) to determine wallet information.
     * @param string|null $walletUrl (deprecated) Anchor can show this to the user when referencing the wallet involved in the withdrawal (ex. in the anchor's transaction history). However, anchors should use client_domain (for non-custodial) and sub value of JWT (for custodial) to determine wallet information.
     * @param string|null $lang Language code specified using RFC 4646.
     * @param Memo|null $refundMemo The memo the anchor must use when sending refund payments back to the user. If not specified, the anchor should use the same memo used by the user to send the original payment.
     * @param string|null $customerId Id of an off-chain account (managed by the anchor) associated with this user's Stellar account (identified by the JWT's sub field). As submitted by the client, not verified. If the anchor supports SEP-12, the customer_id field should match the SEP-12 customer's id. customerId should be passed only when the off-chain id is known to the client, but the relationship between this id and the user's Stellar account is not known to the Anchor.
     * @param array<array-key, mixed>|null $kycFields SEP-09 KYC fields the client passed to the request.
     * @param array<array-key, UploadedFileInterface>|null $kycUploadedFiles SEP-09 KYC files the client passed to the request.
     */
    public function __construct(
        string $account,
        bool $claimableBalanceSupported,
        IdentificationFormatAsset $asset,
        Sep10Jwt $jwtToken,
        ?IdentificationFormatAsset $destinationAsset = null,
        ?float $amount = null,
        ?string $quoteId = null,
        ?Memo $memo = null,
        ?string $walletName = null,
        ?string $walletUrl = null,
        ?string $lang = null,
        ?Memo $refundMemo = null,
        ?string $customerId = null,
        ?array $kycFields = null,
        ?array $kycUploadedFiles = null,
    ) {
        $this->account = $account;
        $this->claimableBalanceSupported = $claimableBalanceSupported;
        $this->asset = $asset;
        $this->jwtToken = $jwtToken;
        $this->destinationAsset = $destinationAsset;
        $this->amount = $amount;
        $this->quoteId = $quoteId;
        $this->memo = $memo;
        $this->walletName = $walletName;
        $this->walletUrl = $walletUrl;
        $this->lang = $lang;
        $this->refundMemo = $refundMemo;
        $this->customerId = $customerId;
        $this->kycFields = $kycFields;
        $this->kycUploadedFiles = $kycUploadedFiles;
    }
}
