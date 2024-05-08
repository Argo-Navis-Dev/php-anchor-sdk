<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\Sep06AssetInfo;
use Soneso\StellarSDK\Memo;

class StartWithdrawRequest
{
    /**
     * @var Sep06AssetInfo $asset The on-chain asset the user wants to withdraw.
     * The value passed must match one of the codes listed in the /info response's withdraw object.
     */
    public Sep06AssetInfo $asset;
    /**
     * @var String $type Type of withdrawal. Can be: crypto, bank_account, cash, mobile, bill_payment or other custom values.
     */
    public string $type;
    /**
     * @var String $sep10Account The stellar or muxed account ID from the jwt token.
     */
    public string $sep10Account;

    /**
     * @var String|null $sep10AccountMemo account memo from the jwt token if available.
     */
    public ?string $sep10AccountMemo = null;

    /**
     * @var String|null $account The Stellar or muxed account the client will use as the source of the
     * withdrawal payment to the anchor. Note that the account specified in this request could differ from the account
     * authenticated via SEP-10.
     */
    public ?string $account;

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
     * @var float|null $amount The amount of the asset the user would like to withdraw.
     */
    public ?float $amount = null;

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
     * Constructor.
     *
     * @param Sep06AssetInfo $asset The on-chain asset the user wants to withdraw.
     * The value passed must match one of the codes listed in the /info response's withdraw object.
     * @param string $type Type of withdrawal. Can be: crypto, bank_account, cash, mobile, bill_payment or other custom values.
     * @param string $sep10Account The stellar or muxed account ID from the jwt token.
     * @param string|null $sep10AccountMemo account memo from the jwt token if available.
     * @param string|null $account The Stellar or muxed account the client will use as the source of the
     * withdrawal payment to the anchor. Note that the account specified in this request could differ from the account
     * authenticated via SEP-10.
     * @param string|null $lang Language code.
     * @param string|null $onChangeCallbackUrl A URL that the anchor should POST a JSON message to when the status
     * property of the transaction created as a result of this request changes.
     * @param float|null $amount The amount of the asset the user would like to withdraw.
     * @param string|null $countryCode Country code of the user's current address.
     * @param Memo|null $refundMemo The memo the anchor must use when sending refund payments back to the user.
     * If not specified, the anchor should use the same memo used by the user to send the original payment.
     * @param string|null $customerId id of an off-chain account (managed by the anchor) associated with this user's
     * Stellar account (identified by the JWT - see: $sep10Account)
     * @param string|null $locationId id of the chosen location to pick up cash
     * @param string|null $clientDomain client domain from the jwt token if available.
     */
    public function __construct(
        Sep06AssetInfo $asset,
        string $type,
        string $sep10Account,
        ?string $sep10AccountMemo = null,
        ?string $account = null,
        ?string $lang = null,
        ?string $onChangeCallbackUrl = null,
        ?float $amount = null,
        ?string $countryCode = null,
        ?Memo $refundMemo = null,
        ?string $customerId = null,
        ?string $locationId = null,
        ?string $clientDomain = null,
    ) {
        $this->asset = $asset;
        $this->type = $type;
        $this->sep10Account = $sep10Account;
        $this->sep10AccountMemo = $sep10AccountMemo;
        $this->account = $account;
        $this->lang = $lang;
        $this->onChangeCallbackUrl = $onChangeCallbackUrl;
        $this->amount = $amount;
        $this->countryCode = $countryCode;
        $this->refundMemo = $refundMemo;
        $this->customerId = $customerId;
        $this->locationId = $locationId;
        $this->clientDomain = $clientDomain;
    }
}
