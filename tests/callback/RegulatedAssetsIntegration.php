<?php

declare(strict_types=1);

namespace ArgoNavis\Test\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\callback\ApprovalActionRequired;
use ArgoNavis\PhpAnchorSdk\callback\ApprovalPending;
use ArgoNavis\PhpAnchorSdk\callback\ApprovalRejected;
use ArgoNavis\PhpAnchorSdk\callback\ApprovalRevised;
use ArgoNavis\PhpAnchorSdk\callback\ApprovalSuccess;
use ArgoNavis\PhpAnchorSdk\callback\IRegulatedAssetsIntegration;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\AllowTrustOperationBuilder;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\PaymentOperation;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\Xdr\XdrTransactionEnvelope;

use function assert;
use function count;

class RegulatedAssetsIntegration implements IRegulatedAssetsIntegration
{
    private string $regulatedAssetCode = 'REG';
    private string $regulatedAssetIssuer = 'GDFVH6M4CBNOSCI3QMUPGUEIW5YA6OYLCSJGSNEYLFL4IB62KZMOMXSF';
    private string $issuerSeed = 'SCB4ZWSB4EYDQO4FU7GAGBJSTH7IHIW4C7Q25CA4JQUTETLIK76K4P64';
    // GDFVH6M4CBNOSCI3QMUPGUEIW5YA6OYLCSJGSNEYLFL4IB62KZMOMXSF

    public function approve(
        string $tx,
    ): ApprovalSuccess | ApprovalRevised | ApprovalPending | ApprovalActionRequired | ApprovalRejected {
        $envelopeXdr = XdrTransactionEnvelope::fromEnvelopeBase64XdrString($tx);
        $envelopeV1 = $envelopeXdr->getV1();
        if ($envelopeV1 === null) {
            return new ApprovalRejected(error: 'Unsupported transaction type');
        }
        $txXdr = $envelopeV1->getTx();

        // Success & Pending
        if (count($txXdr->getOperations()) === 5) {
            /**
             * Operation 1: AllowTrust op where issuer fully authorizes account A, asset X
             * Operation 2: AllowTrust op where issuer fully authorizes account B, asset X
             * Operation 3: Payment from A to B
             * Operation 4: AllowTrust op where issuer fully deauthorizes account B, asset X
             * Operation 5: AllowTrust op where issuer fully deauthorizes account A, asset X
             */
            $transaction = Transaction::fromEnvelopeBase64XdrString($tx);
            $transaction->sign(KeyPair::fromSeed($this->issuerSeed), Network::testnet());

            return new ApprovalSuccess(tx: $transaction->toEnvelopeXdrBase64(), message: 'Tx approved');
        }

        // Revised
        if (count($txXdr->getOperations()) === 3) {
            /**
             * Operation 1: AllowTrust op where issuer fully authorizes account A, asset X
             * Operation 2: AllowTrust op where issuer fully authorizes account B, asset X
             * Operation 3: Payment from A to B
             * (missing) Operation 4 (was not set by client): AllowTrust op where issuer fully deauthorizes account B, asset X
             * (missing) Operation 5 (was not set by client): AllowTrust op where issuer fully deauthorizes account A, asset X
             */
            $transaction = Transaction::fromEnvelopeBase64XdrString($tx);
            assert($transaction instanceof Transaction); // no fee bump
            $sourceAccount = $transaction->getSourceAccount();
            $destinationAccount = null;
            $txBuilder = new TransactionBuilder(
                new Account($sourceAccount->getAccountId(), $transaction->getSequenceNumber()),
            );
            foreach ($transaction->getOperations() as $operation) {
                $txBuilder->addOperation($operation);
                if ($operation instanceof PaymentOperation) {
                    $destinationAccount = $operation->getDestination()->getAccountId();
                }
            }
            assert($destinationAccount !== null);
            $disAllowTrustBOp = (new AllowTrustOperationBuilder(
                $this->regulatedAssetIssuer,
                $this->regulatedAssetCode,
                false,
                false,
            ))->setSourceAccount($destinationAccount)->build();
            $txBuilder->addOperation($disAllowTrustBOp);

            $disAllowTrustAOp = (new AllowTrustOperationBuilder(
                $this->regulatedAssetIssuer,
                $this->regulatedAssetCode,
                false,
                false,
            )
            )->build();
            $txBuilder->addOperation($disAllowTrustAOp);
            $revisedTx = $txBuilder->build();
            $revisedTx->addSignature($transaction->getSignatures()[0]);
            $revisedTx->sign(KeyPair::fromSeed($this->issuerSeed), Network::testnet());

            return new ApprovalRevised(tx: $revisedTx->toEnvelopeXdrBase64(), message: 'Tx revised');
        }

        if (count($txXdr->getOperations()) === 1) {
            $transaction = Transaction::fromEnvelopeBase64XdrString($tx);
            $transaction->sign(KeyPair::fromSeed($this->issuerSeed), Network::testnet());

            return new ApprovalPending(timeout: 100, message: 'Tx pending');
        }

        return new ApprovalActionRequired(
            message: 'Tx action required',
            actionUrl: 'https://test.com/sep08/tx_action',
            actionMethod: 'POST',
            actionFields: ['last_name', 'email_address'],
        );
    }
}
