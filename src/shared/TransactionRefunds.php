<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class TransactionRefunds
{
    /**
     * @var string $amountRefunded The total amount refunded to the user, in units of amountInAsset. If a full refund was issued, this amount should match amountIn.
     */
    public string $amountRefunded;
    /**
     * @var string $amountFee The total amount charged in fees for processing all refund payments, in units of amountInAsset. The sum of all fee values in the payments object list should equal this value.
     */
    public string $amountFee;
    /**
     * @var array<TransactionRefundPayment> $payments A list of TransactionRefundPayment objects containing information on the individual payments made back to the user as refunds.
     */
    public array $payments;

    /**
     * @param string $amountRefunded The total amount refunded to the user, in units of amountInAsset. If a full refund was issued, this amount should match amountIn.
     * @param string $amountFee The total amount charged in fees for processing all refund payments, in units of amountInAsset. The sum of all fee values in the payments object list should equal this value.
     * @param array<TransactionRefundPayment> $payments A list of TransactionRefundPayment objects containing information on the individual payments made back to the user as refunds.
     */
    public function __construct(string $amountRefunded, string $amountFee, array $payments)
    {
        $this->amountRefunded = $amountRefunded;
        $this->amountFee = $amountFee;
        $this->payments = $payments;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $json
         */
        $json = ['amount_refunded' => $this->amountRefunded,
            'amount_fee' => $this->amountFee,
        ];

        /**
         * @var array<string, mixed> $paymentsData
         */
        $paymentsData = [];
        foreach ($this->payments as $payment) {
            $paymentsData[] = $payment->toJson();
        }
        $json['payments'] = $paymentsData;

        return $json;
    }
}
