<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\shared\IdentificationFormatAsset;
use ArgoNavis\PhpAnchorSdk\shared\Sep31AssetInfo;
use Soneso\StellarSDK\Memo;

class Sep31PostTransactionRequest
{
    /**
     * @var string $accountId account id of the sending anchor received via SEP-10.
     */
    public string $accountId;

    /**
     * @var string|null $accountMemo account memo of the sending anchor received via SEP-10.
     */
    public ?string $accountMemo = null;

    /**
     * @var float $amount Amount of the Stellar asset the Sending Anchor intends to send the Receiving Anchor.
     */
    public float $amount;

    /**
     * @var Sep31AssetInfo $asset The asset the Sending Anchor intends to send.
     */
    public Sep31AssetInfo $asset;

    /**
     * @var IdentificationFormatAsset|null $destinationAsset The off-chain asset the Receiving Anchor will
     * deliver to the Receiving Client.
     */
    public ?IdentificationFormatAsset $destinationAsset = null;

    /**
     * @var string|null $quoteId The ID returned from a SEP-38 POST /quote response.
     */
    public ?string $quoteId = null;

    /**
     * @var string|null $senderId The ID included in the SEP-12 PUT /customer response for the Sending Client.
     */
    public ?string $senderId = null;

    /**
     * @var string|null $receiverId The ID included in the SEP-12 PUT /customer response for the Receiving Client.
     */
    public ?string $receiverId = null;

    /**
     * @var string|null $lang Language code specified using ISO 639-1. Defaults to en.
     */
    public ?string $lang = 'en';

    /**
     * @var Memo|null $refundMemo The memo the Receiving Anchor must use when sending refund payments back to the
     * Sending Anchor. If not specified, the Receiving Anchor should use the same memo the Sending Anchor
     * used to send the original payment.
     */
    public ?Memo $refundMemo = null;

    /**
     * @var string|null client domain from the jwt token if available.
     */
    public ?string $clientDomain = null;

    /**
     * Constructor.
     *
     * @param string $accountId account id of the user received via SEP-10.
     * @param string|null $accountMemo account memo of the user received via SEP-10.
     * @param float $amount Amount of the Stellar asset the Sending Anchor intends to send the Receiving Anchor.
     * @param Sep31AssetInfo $asset The asset the Sending Anchor intends to send.
     * @param IdentificationFormatAsset|null $destinationAsset The off-chain asset the Receiving Anchor will
     *  deliver to the Receiving Client.
     * @param string|null $quoteId The ID returned from a SEP-38 POST /quote response.
     * @param string|null $senderId The ID included in the SEP-12 PUT /customer response for the Sending Client.
     * @param string|null $receiverId The ID included in the SEP-12 PUT /customer response for the Receiving Client.
     * @param string|null $lang Language code specified using ISO 639-1. Defaults to en.
     * @param Memo|null $refundMemo The memo the Receiving Anchor must use when sending refund payments back to the
     *  Sending Anchor. If not specified, the Receiving Anchor should use the same memo the Sending Anchor
     *  used to send the original payment.
     * @param string|null $clientDomain client domain from the jwt token if available.
     */
    public function __construct(
        string $accountId,
        ?string $accountMemo,
        float $amount,
        Sep31AssetInfo $asset,
        ?IdentificationFormatAsset $destinationAsset = null,
        ?string $quoteId = null,
        ?string $senderId = null,
        ?string $receiverId = null,
        ?string $lang = null,
        ?Memo $refundMemo = null,
        ?string $clientDomain = null,
    ) {
        $this->accountId = $accountId;
        $this->accountMemo = $accountMemo;
        $this->amount = $amount;
        $this->asset = $asset;
        $this->destinationAsset = $destinationAsset;
        $this->quoteId = $quoteId;
        $this->senderId = $senderId;
        $this->receiverId = $receiverId;
        $this->lang = $lang;
        $this->refundMemo = $refundMemo;
        $this->clientDomain = $clientDomain;
    }
}
