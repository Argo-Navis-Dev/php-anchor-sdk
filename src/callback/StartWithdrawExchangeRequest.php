<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep06AssetInfo;
use Soneso\StellarSDK\Memo;

class StartWithdrawExchangeRequest
{
    /**
     * @var Sep06AssetInfo $sourceAsset The on-chain asset the user wants to withdraw.
     */
    public Sep06AssetInfo $sourceAsset;

    /**
     * @var IdentificationFormatAsset $destinationAsset The off-chain asset the Anchor will deliver to the user's
     * account. The value must match one of the asset values included in a
     * SEP-38 GET /prices?sell_asset=stellar:<source_asset>:<asset_issuer>
     */
    public IdentificationFormatAsset $destinationAsset;

    /**
     * @var float $amount The amount of the on-chain asset ($sourceAsset) the user would like to send to the
     * anchor's Stellar account.
     */
    public float $amount;


    /**
     * @var string $sep10Account The stellar or muxed account ID from the jwt token.
     */
    public string $sep10Account;

    /**
     * @var string|null $sep10AccountMemo account memo from the jwt token if available.
     */
    public ?string $sep10AccountMemo = null;

    /**
     * @var string|null $account The Stellar or muxed account of the user that wants to do the withdrawal.
     * Note that the account specified in this request could differ from the account authenticated via SEP-10.
     */
    public ?string $account = null;


    /**
     * @var string|null $type Type of withdrawal. Can be: crypto, bank_account, cash, mobile,
     * bill_payment or other custom values.
     */
    public ?string $type = null;

    /**
     * @var string|null $quoteId The id returned from a SEP-38 POST /quote response.
     */
    public ?string $quoteId = null;


    /**
     * @var String|null $lang Language code.
     */
    public ?string $lang = null;

    /**
     * @var String|null $onChangeCallbackUrl A URL that the anchor should POST a JSON message to when the status
     * property of the transaction created as a result of this request changes.
     */
    public ?string $onChangeCallbackUrl = null;

    /**
     * @var String|null $countryCode country code of the user's current address.
     */
    public ?string $countryCode = null;

    /**
     * @var Memo|null $refundMemo The memo the anchor must use when sending refund payments back to the user.
     * If not specified, the anchor should use the same memo used by the user to send the original payment.
     */
    public ?Memo $refundMemo = null;

    /**
     * @var String|null id of an off-chain account (managed by the anchor) associated with this user's
     * Stellar account (identified by the JWT - see: $sep10Account)
     */
    public ?string $customerId = null;

    /**
     * @var String|null $locationId id of the chosen location to pick up cash
     */
    public ?string $locationId = null;

    /**
     * @var String|null client domain from the jwt token if available.
     */
    public ?string $clientDomain = null;

    /**
     * @param Sep06AssetInfo $sourceAsset The on-chain asset the user wants to withdraw.
     * @param IdentificationFormatAsset $destinationAsset The off-chain asset the Anchor will deliver to the user's
     *  account. The value must match one of the asset values included in a
     *  SEP-38 GET /prices?sell_asset=stellar:<source_asset>:<asset_issuer>
     * @param float $amount The amount of the on-chain asset ($sourceAsset) the user would like to send to the
     *  anchor's Stellar account.
     * @param string $sep10Account The stellar or muxed account ID from the jwt token.
     * @param string|null $sep10AccountMemo account memo from the jwt token if available.
     * @param string|null $account The Stellar or muxed account of the user that wants to do the withdrawal.
     *  Note that the account specified in this request could differ from the account authenticated via SEP-10.
     * @param string|null $type Type of withdrawal. Can be: crypto, bank_account, cash, mobile, bill_payment or other custom values.
     * @param string|null $quoteId The id returned from a SEP-38 POST /quote response.
     * @param string|null $lang Language code.
     * @param string|null $onChangeCallbackUrl A URL that the anchor should POST a JSON message to when the status
     *  property of the transaction created as a result of this request changes.
     * @param string|null $countryCode Country code of the user's current address.
     * @param Memo|null $refundMemo The memo the anchor must use when sending refund payments back to the user.
     *  If not specified, the anchor should use the same memo used by the user to send the original payment.
     * @param string|null $customerId id of an off-chain account (managed by the anchor) associated with this user's
     *  Stellar account (identified by the JWT - see: $sep10Account)
     * @param string|null $locationId id of the chosen location to pick up cash
     * @param string|null $clientDomain client domain from the jwt token if available.
     */
    public function __construct(
        Sep06AssetInfo $sourceAsset,
        IdentificationFormatAsset $destinationAsset,
        float $amount,
        string $sep10Account,
        ?string $sep10AccountMemo = null,
        ?string $account = null,
        ?string $type = null,
        ?string $quoteId = null,
        ?string $lang = null,
        ?string $onChangeCallbackUrl = null,
        ?string $countryCode = null,
        ?Memo $refundMemo = null,
        ?string $customerId = null,
        ?string $locationId = null,
        ?string $clientDomain = null,
    ) {
        $this->sourceAsset = $sourceAsset;
        $this->destinationAsset = $destinationAsset;
        $this->amount = $amount;
        $this->sep10Account = $sep10Account;
        $this->sep10AccountMemo = $sep10AccountMemo;
        $this->account = $account;
        $this->type = $type;
        $this->quoteId = $quoteId;
        $this->lang = $lang;
        $this->onChangeCallbackUrl = $onChangeCallbackUrl;
        $this->countryCode = $countryCode;
        $this->refundMemo = $refundMemo;
        $this->customerId = $customerId;
        $this->locationId = $locationId;
        $this->clientDomain = $clientDomain;
    }
}
