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
 * Prepared SEP-24 deposit request data.
 * See also <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0024.md#deposit-2">SEP-024 deposit</a>
 */
class InteractiveDepositRequest
{
    /**
     * @var string $account The Stellar account id or muxed account id the client wants to use as the destination of the payment sent by the anchor. Defaults to the account authenticated via SEP-10 if not specified by the client.
     */
    public string $account;

    /**
     * @var bool $claimableBalanceSupported True if the client supports receiving deposit transactions as a claimable balance, false otherwise.
     */
    public bool $claimableBalanceSupported;

    /**
     * @var IdentificationFormatAsset $asset Stellar asset the user wants to receive for their deposit with the anchor.
     */
    public IdentificationFormatAsset $asset;

    /**
     * @var Sep10Jwt $jwtToken The jwt token from the SEP-10 authentication. Contains data that identifies the user and client.
     */
    public Sep10Jwt $jwtToken;

    /**
     * @var IdentificationFormatAsset|null $sourceAsset The asset user wants to send. Note, that this is the asset user initially holds (off-chain or fiat asset).If this is not provided, it should be collected in the interactive flow.
     */
    public ?IdentificationFormatAsset $sourceAsset = null;

    /**
     * @var float|null $amount Amount of asset requested to deposit. If this is not provided it should be collected in the interactive flow.
     */
    public ?float $amount = null;

    /**
     * @var string|null $quoteId The id returned from a SEP-38 POST /quote response.
     */
    public ?string $quoteId = null;

    /**
     * @var Memo|null $memo The memo to attach to the Stellar transaction.
     */
    public ?Memo $memo = null;


    /**
     * @var string|null $walletName (deprecated) In communications / pages about the deposit, anchor should display the wallet name to the user to explain where funds are going. However, anchors should use client_domain (for non-custodial) and sub value of JWT (for custodial) to determine wallet information.
     */
    public ?string $walletName = null;

    /**
     * @var string|null (deprecated) Anchor should link to this when notifying the user that the transaction has completed. However, anchors should use client_domain (for non-custodial) and sub value of JWT (for custodial) to determine wallet information.
     */
    public ?string $walletUrl = null;

    /**
     * @var string|null $lang Language code specified using RFC 4646.
     */
    public ?string $lang = null;

    /**
     * @var string|null Id of an off-chain account (managed by the anchor) associated with this user's Stellar account (identified by the JWT's sub field). As submitted by the client, not verified.
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
     * @param string $account The Stellar account id or muxed account id the client wants to use as the destination of the payment sent by the anchor. Defaults to the account authenticated via SEP-10 if not specified.
     * @param bool $claimableBalanceSupported True if the client supports receiving deposit transactions as a claimable balance, false otherwise.
     * @param IdentificationFormatAsset $asset Stellar asset the user wants to receive for their deposit with the anchor.
     * @param Sep10Jwt $jwtToken The jwt token from the SEP-10 authentication. Contains data that identifies the user and client.
     * @param IdentificationFormatAsset|null $sourceAsset The asset user wants to send. Note, that this is the asset user initially holds (off-chain or fiat asset).If this is not provided, it should be collected in the interactive flow.
     * @param float|null $amount Amount of asset requested to deposit. If this is not provided it should be collected in the interactive flow.
     * @param string|null $quoteId The id returned from a SEP-38 POST /quote response.
     * @param Memo|null $memo Memo to attach to transaction.
     * @param string|null $walletName (deprecated) In communications / pages about the deposit, anchor should display the wallet name to the user to explain where funds are going. However, anchors should use client_domain (for non-custodial) and sub value of JWT (for custodial) to determine wallet information.
     * @param string|null $walletUrl (deprecated) Anchor should link to this when notifying the user that the transaction has completed. However, anchors should use client_domain (for non-custodial) and sub value of JWT (for custodial) to determine wallet information.
     * @param string|null $lang Language code specified using RFC 4646.
     * @param string|null $customerId id of an off-chain account (managed by the anchor) associated with this user's Stellar account (identified by the JWT's sub field).
     * @param array<array-key, mixed>|null $kycFields SEP-09 KYC fields the client passed to the request.
     * @param array<array-key, UploadedFileInterface>|null $kycUploadedFiles SEP-09 KYC files the client passed to the request.
     */
    public function __construct(
        string $account,
        bool $claimableBalanceSupported,
        IdentificationFormatAsset $asset,
        Sep10Jwt $jwtToken,
        ?IdentificationFormatAsset $sourceAsset = null,
        ?float $amount = null,
        ?string $quoteId = null,
        ?Memo $memo = null,
        ?string $walletName = null,
        ?string $walletUrl = null,
        ?string $lang = null,
        ?string $customerId = null,
        ?array $kycFields = null,
        ?array $kycUploadedFiles = null,
    ) {
        $this->account = $account;
        $this->claimableBalanceSupported = $claimableBalanceSupported;
        $this->asset = $asset;
        $this->jwtToken = $jwtToken;
        $this->sourceAsset = $sourceAsset;
        $this->amount = $amount;
        $this->quoteId = $quoteId;
        $this->memo = $memo;
        $this->walletName = $walletName;
        $this->walletUrl = $walletUrl;
        $this->lang = $lang;
        $this->customerId = $customerId;
        $this->kycFields = $kycFields;
        $this->kycUploadedFiles = $kycUploadedFiles;
    }
}
