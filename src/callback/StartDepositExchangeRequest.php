<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep06AssetInfo;
use Soneso\StellarSDK\Memo;

class StartDepositExchangeRequest
{
    /**
     * @var Sep06AssetInfo $destinationAsset the on-chain asset the user wants to
     * get from the Anchor after doing an off-chain deposit.
     */
    public Sep06AssetInfo $destinationAsset;

    /**
     * @var IdentificationFormatAsset $sourceAsset off-chain asset the Anchor will receive from the user.
     * The value must match one of the asset values included in a
     * SEP-38 GET /prices?buy_asset=stellar:<destination_asset>:<asset_issuer>
     */
    public IdentificationFormatAsset $sourceAsset;

    /**
     * @var float $amount The amount of the $sourceAsset the user would like to deposit to the anchor's
     * off-chain account. Should be equals to quote.sell_amount if a quote_id was used.
     */
    public float $amount;

    /**
     * @var string $account The stellar or muxed account ID of the user that wants to deposit.
     * This is where the asset token will be sent. Note that the account specified in this request could
     * differ from the account authenticated via SEP-10.
     */
    public string $account;

    /**
     * @var string $sep10Account The stellar or muxed account ID from the jwt token.
     */
    public string $sep10Account;

    /**
     * @var string|null $sep10AccountMemo account memo from the jwt token if available.
     */
    public ?string $sep10AccountMemo = null;

    /**
     * @var Memo|null $memo Value of memo to attach to transaction. Because a memo can be specified in the SEP-10 JWT
     * for Shared Accounts, this memo can be different from the values included in the SEP-10 JWT.
     */
    public ?Memo $memo = null;

    /**
     * @var string|null $quoteId The id returned from a SEP-38 POST /quote response. If this parameter is provided and
     * the user delivers the deposit funds to the Anchor before the quote expiration, the Anchor should respect the
     * conversion rate agreed in that quote.
     */
    public ?string $quoteId = null;

    /**
     * @var string|null Email address of depositor. If desired, an anchor can use this to send email updates to
     * the user about the deposit.
     */
    public ?string $email = null;

    /**
     * @var string|null Type of deposit.
     */
    public ?string $type = null;

    /**
     * @var string|null $lang Language code.
     */
    public ?string $lang = null;

    /**
     * @var string|null $onChangeCallbackUrl A URL that the anchor should POST a JSON message to when the status
     * property of the transaction created as a result of this request changes.
     */
    public ?string $onChangeCallbackUrl = null;

    /**
     * @var string|null $countryCode country code of the user's current address.
     */
    public ?string $countryCode = null;

    /**
     * @var bool|null $claimableBalanceSupported true if the client supports receiving deposit transactions
     * as a claimable balance.
     */
    public ?bool $claimableBalanceSupported = null;

    /**
     * @var string|null id of an off-chain account (managed by the anchor) associated with this user's
     * Stellar account (identified by the JWT - see: $sep10Account)
     */
    public ?string $customerId = null;

    /**
     * @var string|null $locationId id of the chosen location to drop off cash
     */
    public ?string $locationId = null;

    /**
     * @var string|null client domain from the jwt token if available.
     */
    public ?string $clientDomain = null;

    /**
     * @param Sep06AssetInfo $destinationAsset The on-chain asset the user wants to get from the Anchor after doing
     * an off-chain deposit.
     * @param IdentificationFormatAsset $sourceAsset off-chain asset the Anchor will receive from the user.
     * The value must match one of the asset values included in a
     * SEP-38 GET /prices?buy_asset=stellar:<destination_asset>:<asset_issuer>
     * @param float $amount The amount of the $sourceAsset the user would like to deposit to the anchor's
     * off-chain account. Should be equals to quote.sell_amount if a quote_id was used.
     * @param string $account The stellar or muxed account ID of the user that wants to deposit.
     * This is where the asset token will be sent. Note that the account specified in this request could
     * differ from the account authenticated via SEP-10.
     * @param string $sep10Account The stellar or muxed account ID from the jwt token.
     * @param string|null $sep10AccountMemo The account memo from the jwt token if available.
     * @param Memo|null $memo Value of memo to attach to transaction. Because a memo can be specified in the SEP-10 JWT
     * for Shared Accounts, this memo can be different from the values included in the SEP-10 JWT.
     * @param string|null $quoteId The id returned from a SEP-38 POST /quote response. If this parameter is provided and
     * the user delivers the deposit funds to the Anchor before the quote expiration, the Anchor should respect the
     * conversion rate agreed in that quote.
     * @param string|null $email Email address of depositor. If desired, an anchor can use this to send email updates to
     * the user about the deposit.
     * @param string|null $type Type of deposit.
     * @param string|null $lang Language code.
     * @param string|null $onChangeCallbackUrl A URL that the anchor should POST a JSON message to when the status
     * property of the transaction created as a result of this request changes.
     * @param string|null $countryCode country code of the user's current address.
     * @param bool|null $claimableBalanceSupported true if the client supports receiving deposit transactions
     * as a claimable balance.
     * @param string|null $customerId id of an off-chain account (managed by the anchor) associated with this user's
     * Stellar account (identified by the JWT - see: $sep10Account)
     * @param string|null $locationId id of the chosen location to drop off cash
     * @param string|null $clientDomain client domain from the jwt token if available.
     */
    public function __construct(
        Sep06AssetInfo $destinationAsset,
        IdentificationFormatAsset $sourceAsset,
        float $amount,
        string $account,
        string $sep10Account,
        ?string $sep10AccountMemo = null,
        ?Memo $memo = null,
        ?string $quoteId = null,
        ?string $email = null,
        ?string $type = null,
        ?string $lang = null,
        ?string $onChangeCallbackUrl = null,
        ?string $countryCode = null,
        ?bool $claimableBalanceSupported = null,
        ?string $customerId = null,
        ?string $locationId = null,
        ?string $clientDomain = null,
    ) {
        $this->destinationAsset = $destinationAsset;
        $this->sourceAsset = $sourceAsset;
        $this->amount = $amount;
        $this->account = $account;
        $this->sep10Account = $sep10Account;
        $this->sep10AccountMemo = $sep10AccountMemo;
        $this->memo = $memo;
        $this->quoteId = $quoteId;
        $this->email = $email;
        $this->type = $type;
        $this->lang = $lang;
        $this->onChangeCallbackUrl = $onChangeCallbackUrl;
        $this->countryCode = $countryCode;
        $this->claimableBalanceSupported = $claimableBalanceSupported;
        $this->customerId = $customerId;
        $this->locationId = $locationId;
        $this->clientDomain = $clientDomain;
    }
}
