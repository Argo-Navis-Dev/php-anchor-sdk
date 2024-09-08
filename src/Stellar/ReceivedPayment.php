<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Stellar;

use phpseclib3\Math\BigInteger;

use function array_keys;
use function array_map;
use function implode;
use function sprintf;

class ReceivedPayment
{
    /**
     * @var string $assetCode The asset code of the asset received. 'native' if Stellar Lumens - XLM have been received.
     */
    public string $assetCode;
    /**
     * @var string|null $assetIssuer The asset issuer Stellar account id of the asset received.
     * null if Stellar Lumens - XLM have been received.
     */
    public ?string $assetIssuer = null;

    /**
     * @var string|null $memoValue The memo value as string of the Memo used in the Stellar Transaction that included
     * this payment. If memo type is 'hash' or 'return' it is the base64 encoded string of the memo value.
     */
    public ?string $memoValue = null;

    /**
     * @var string|null $memoType The memo type of the Memo used in the Stellar Transaction that included this payment.
     * (possible types: 'text', 'id', 'hash', 'return')
     */
    public ?string $memoType = null;

    /**
     * @var BigInteger $amountIn The Stellar payment amount (Stellar uses 7 decimals places of precision).
     */
    public BigInteger $amountIn;

    /**
     * @var string $amountInAsDecimalString The received payment amount as decimal string with 7 places of precision.
     * (`$amountIn` payment amount divided by 10000000). E.g. 25.52 USDC would be `$amountIn`: 2552000000, and
     * `$amountInAsDecimalString`: 25.52000000
     */
    public string $amountInAsDecimalString;

    /**
     * @var string $senderAccountId The Stellar account id of the payment sender. It is the source account of the payment
     * operation if set, otherwise source account of the transaction that includes this payment.
     */
    public string $senderAccountId;

    /**
     * @var string $receiverAccountId The Stellar account id of the payment receiver.
     */
    public string $receiverAccountId;

    /**
     * @var string $stellarTransactionId Id/Hash of the Stellar transaction that includes this payment.
     */
    public string $stellarTransactionId;

    /**
     * @var string $transactionEnvelopeXdr The base64 encoded Stellar transaction envelope xdr that contains
     * this payment.
     */
    public string $transactionEnvelopeXdr;

    /**
     * @var string $transactionResultXdr The base 64 encoded transaction result xdr of the Stellar transaction that
     * contains this payment.
     */
    public string $transactionResultXdr;

    /**
     * Constructor.
     *
     * @param string $assetCode asset code of the asset received. 'native' if Stellar Lumens - XLM have been received.
     * @param string|null $assetIssuer asset issuer Stellar account id of the asset received.
     *  null if Stellar Lumens - XLM have been received.
     * @param string|null $memoValue memo value as string of the Memo used in the Stellar Transaction that included
     *  this payment. If memo type is 'hash' or 'return' it is the base64 encoded string of the memo value.
     * @param string|null $memoType memo type of the Memo used in the Stellar Transaction that included this payment.
     *  (possible types: 'text', 'id', 'hash', 'return')
     * @param BigInteger $amountIn Stellar payment amount (Stellar uses 7 decimals places of precision).
     * @param string $amountInAsDecimalString the received payment amount as decimal string with 7 places of precision.
     *  ($amountIn payment amount divided by 10000000).
     * @param string $senderAccountId Stellar account id of the payment sender. It is the source account of the payment
     * operation if set, otherwise source account of the transaction that includes this payment.
     * @param string $receiverAccountId Stellar account id of the payment receiver.
     * @param string $stellarTransactionId Id/Hash of the Stellar transaction that includes this payment.
     * @param string $transactionEnvelopeXdr The base64 encoded Stellar transaction envelope xdr that contains
     * this payment.
     * @param string $transactionResultXdr The base 64 encoded transaction result xdr of the Stellar transaction that
     * contains this payment.
     */
    public function __construct(
        string $assetCode,
        ?string $assetIssuer,
        ?string $memoValue,
        ?string $memoType,
        BigInteger $amountIn,
        string $amountInAsDecimalString,
        string $senderAccountId,
        string $receiverAccountId,
        string $stellarTransactionId,
        string $transactionEnvelopeXdr,
        string $transactionResultXdr,
    ) {
        $this->assetCode = $assetCode;
        $this->assetIssuer = $assetIssuer;
        $this->memoValue = $memoValue;
        $this->memoType = $memoType;
        $this->amountIn = $amountIn;
        $this->amountInAsDecimalString = $amountInAsDecimalString;
        $this->senderAccountId = $senderAccountId;
        $this->receiverAccountId = $receiverAccountId;
        $this->stellarTransactionId = $stellarTransactionId;
        $this->transactionEnvelopeXdr = $transactionEnvelopeXdr;
        $this->transactionResultXdr = $transactionResultXdr;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        // this is needed for array_diff
        /**
         * @var array<string,string> $data
         */
        $data = ['assetCode' => $this->assetCode,
            'assetIssuer' => $this->assetIssuer,
            'memoValue' => $this->memoValue,
            'memoType' => $this->memoType,
            'amountIn' => $this->amountIn->toString(),
            'amountInAsDecimalString' => $this->amountInAsDecimalString,
            'senderAccountId' => $this->senderAccountId,
            'receiverAccountId' => $this->receiverAccountId,
            'stellarTransactionId' => $this->stellarTransactionId,
            'transactionEnvelopeXdr' => $this->transactionEnvelopeXdr,
            'transactionResultXdr' => $this->transactionResultXdr,
        ];

        return implode(', ', array_map(
            fn ($v, $k) => sprintf("%s='%s'", $k, $v),
            $data,
            array_keys($data),
        ));
    }
}
