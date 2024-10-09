<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep08;

use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\Xdr\XdrEnvelopeType;
use Soneso\StellarSDK\Xdr\XdrTransactionEnvelope;
use Throwable;

use function count;
use function is_string;
use function json_encode;

class ApprovalRequest
{
    /**
     * The base64 encoded transaction envelope XDR signed by the user.
     * This is the transaction that will be tested for compliance and signed on success.
     *
     * @var string xdr encoded transaction envelope (base64).
     */
    public string $tx;

    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

    public function __construct(string $tx)
    {
        $this->tx = $tx;
    }

    /**
     * Creates a ApprovalRequest object from the given json array.
     *
     * @param array<array-key, mixed> $data the array to parse the data from.
     *
     * @return ApprovalRequest the parsed validation request.
     *
     * @throws InvalidRequestData if the contained data is invalid in some way.
     */
    public static function fromDataArray(array $data): ApprovalRequest
    {
        if (!isset($data['tx'])) {
             throw new InvalidRequestData('Transaction is not set');
        }
        $transaction = $data['tx'];
        if (!is_string($transaction)) {
            throw new InvalidRequestData('Transaction is not a string');
        }

        try {
            self::getLogger()->debug(
                'Parsing transaction from base 64 XDR envelope.',
                ['context' => 'sep08', 'envelop' => $transaction],
            );
            $envelope = XdrTransactionEnvelope::fromEnvelopeBase64XdrString($transaction);
        } catch (Throwable $e) {
            throw new InvalidRequestData('Invalid transaction');
        }

        $txV1 = $envelope->getV1();
        $feeBump = $envelope->getFeeBump();
        self::getLogger()->debug(
            'Transaction details.',
            ['context' => 'sep08', 'tx_v1' => ($txV1 !== null ? json_encode($txV1) : null),
                'fee_bump' => ($feeBump !== null ? json_encode($feeBump) : null),
            ],
        );

        if ($envelope->getType()->getValue() === XdrEnvelopeType::ENVELOPE_TYPE_TX && $txV1 !== null) {
            if (count($txV1->getSignatures()) === 0) {
                throw new InvalidRequestData('Transaction has no signatures.');
            }
        } elseif (
            $envelope->getType()->getValue() === XdrEnvelopeType::ENVELOPE_TYPE_TX_FEE_BUMP &&
            $feeBump !== null
        ) {
            $innerTxV1 = $feeBump->getTx()->getInnerTx()->getV1();
            if (count($innerTxV1->getSignatures()) === 0) {
                throw new InvalidRequestData('Inner transaction has no signatures.');
            }
        } else {
            throw new InvalidRequestData('Transaction has invalid type.');
        }

        return new ApprovalRequest($transaction);
    }

    /**
     * Sets the logger in static context.
     */
    public static function setLogger(?LoggerInterface $logger = null): void
    {
        self::$logger = $logger ?? new NullLogger();
    }

    /**
     * Returns the logger (initializes if null).
     */
    private static function getLogger(): LoggerInterface
    {
        if (!isset(self::$logger)) {
            self::$logger = new NullLogger();
        }

        return self::$logger;
    }
}
