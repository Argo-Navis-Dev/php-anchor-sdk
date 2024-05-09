<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

class StartWithdrawResponse
{
    /**
     * @var string $id The anchor's ID for this withdrawal. The wallet will use this ID to query the
     * /transaction endpoint to check status of the request.
     */
    public string $id;

    /**
     * @var string|null $accountId The account the user should send its token back to.
     * This field can be omitted if the anchor cannot provide this information at the time of the request.
     */
    public ?string $accountId = null;

    /**
     * @var string|null $memoType Type of memo to attach to transaction, one of text, id or hash.
     */
    public ?string $memoType = null;

    /**
     * @var string|null $memo Value of memo to attach to transaction, for hash this should be base64-encoded.
     * The anchor should use this memo to match the Stellar transaction with the database entry associated
     * created to represent it.
     */
    public ?string $memo = null;

    /**
     * @var int|null $eta Estimate of how long the withdrawal will take to credit in seconds.
     */
    public ?int $eta = null;

    /**
     * @var float|null $minAmount Minimum amount of an asset that a user can withdraw.
     */
    public ?float $minAmount = null;

    /**
     * @var float|null $maxAmount Maximum amount of asset that a user can withdraw.
     */
    public ?float $maxAmount = null;

    /**
     * @var float|null $feeFixed If there is a fee for withdraw. In units of the withdrawn asset.
     */
    public ?float $feeFixed = null;

    /**
     * @var float|null $feePercent If there is a percent fee for withdraw.
     */
    public ?float $feePercent = null;

    /**
     * @var string|null $extraInfo Any additional data needed as an input for this withdraw, example: Bank Name.
     */
    public ?string $extraInfo = null;

    /**
     * @param string $id The anchor's ID for this withdrawal. The wallet will use this ID to query the
     *  /transaction endpoint to check status of the request.
     * @param string|null $accountId The account the user should send its token back to.
     *  This field can be omitted if the anchor cannot provide this information at the time of the request.
     * @param string|null $memoType Type of memo to attach to transaction, one of text, id or hash.
     * @param string|null $memo Value of memo to attach to transaction, for hash this should be base64-encoded.
     *  The anchor should use this memo to match the Stellar transaction with the database entry associated
     *  created to represent it.
     * @param int|null $eta Estimate of how long the withdrawal will take to credit in seconds.
     * @param float|null $minAmount Minimum amount of an asset that a user can withdraw.
     * @param float|null $maxAmount If there is a fee for withdraw. In units of the withdrawn asset.
     * @param float|null $feeFixed If there is a percent fee for withdraw.
     * @param float|null $feePercent If there is a percent fee for withdraw.
     * @param string|null $extraInfo Any additional data needed as an input for this withdraw, example: Bank Name.
     */
    public function __construct(
        string $id,
        ?string $accountId = null,
        ?string $memoType = null,
        ?string $memo = null,
        ?int $eta = null,
        ?float $minAmount = null,
        ?float $maxAmount = null,
        ?float $feeFixed = null,
        ?float $feePercent = null,
        ?string $extraInfo = null,
    ) {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->memoType = $memoType;
        $this->memo = $memo;
        $this->eta = $eta;
        $this->minAmount = $minAmount;
        $this->maxAmount = $maxAmount;
        $this->feeFixed = $feeFixed;
        $this->feePercent = $feePercent;
        $this->extraInfo = $extraInfo;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $json
         */
        $json = ['id' => $this->id];

        if ($this->accountId !== null) {
            $json['account_id'] = $this->accountId;
        }

        if ($this->memo !== null) {
            $json['memo'] = $this->memo;
        }

        if ($this->memoType !== null) {
            $json['memo_type'] = $this->memoType;
        }

        if ($this->eta !== null) {
            $json['eta'] = $this->eta;
        }

        if ($this->minAmount !== null) {
            $json['min_amount'] = $this->minAmount;
        }

        if ($this->maxAmount !== null) {
            $json['max_amount'] = $this->maxAmount;
        }

        if ($this->feeFixed !== null) {
            $json['fee_fixed'] = $this->feeFixed;
        }

        if ($this->feePercent !== null) {
            $json['fee_percent'] = $this->feePercent;
        }

        if ($this->extraInfo !== null) {
            $json['extra_info'] = ['message' => $this->extraInfo];
        }

        return $json;
    }
}
