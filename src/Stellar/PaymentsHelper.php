<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Stellar;

use ArgoNavis\PhpAnchorSdk\exception\AccountNotFound;
use Soneso\StellarSDK\Asset;
use Soneso\StellarSDK\AssetTypeCreditAlphanum;
use Soneso\StellarSDK\Exceptions\HorizonRequestException;
use Soneso\StellarSDK\MuxedAccount;
use Soneso\StellarSDK\Responses\Transaction\TransactionResponse;
use Soneso\StellarSDK\Responses\Transaction\TransactionsPageResponse;
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\Util\StellarAmount;
use Soneso\StellarSDK\Xdr\XdrMuxedAccount;
use Soneso\StellarSDK\Xdr\XdrOperation;
use Soneso\StellarSDK\Xdr\XdrOperationResult;
use phpseclib3\Math\BigInteger;

use function array_merge;
use function count;
use function end;

class PaymentsHelper
{
    /**
     * This function queries the received payments for a given Stellar account.
     * It takes into account both normal Stellar transactions and fee bump transactions.
     * It finds both normal received payments and received path payments.
     *
     * The function communicates with a horizon instance given by the parameter horizonUrl to query the
     * received payments.
     *
     * This functionality is particularly useful to find out whether incoming payments for SEP-6 withdrawal and
     * withdrawal-exchange, SEP-24 withdrawal and withdrawal-exchange and SEP-31 send anchor transactions
     * have been received.
     *
     * The function proceeds as follows: All Stellar transactions for the receiving account starting from the given
     * cursor on are loaded and checked. If no cursor is specified, all Stellar transactions of the
     * receiving account are loaded (cursor = 0). Each loaded Stellar transaction is checked to see if it
     * contains payments received by the receiver account. For each payment found that matched,
     * the relevant values are extracted (amount in, source account that sent the payment, stellar transaction id,
     * etc.) and set to a new created object (ReceivedPayment) that is added to the list of found payments.
     * These are then returned as a result in a ReceivedPaymentsQueryResult object, which contains further important
     * information, such as the paging token of the last transaction found, which can then be used as a (start) cursor
     * in a successive query to shorten the processing time and avoid finding the same payments as in the previous
     * query.
     *
     * @param string $horizonUrl the url of the horizon instance to be used to query the received payments.
     * @param string $receiverAccountId The account to query the received payments for.
     * @param string|null $cursor The (start) cursor to be applied when querying all transactions for the receiver account.
     *
     * @return ReceivedPaymentsQueryResult The result object containing the found received payments.
     *
     * @throws AccountNotFound if the receiver account does not exist in the horizon instance.
     * @throws HorizonRequestException if any horizon request exception occurred.
     */
    public static function queryReceivedPayments(
        string $horizonUrl,
        string $receiverAccountId,
        ?string $cursor = null,
    ): ReceivedPaymentsQueryResult {
        $sdk = new StellarSDK($horizonUrl);

        // first check if account exists
        if (!$sdk->accountExists($receiverAccountId)) {
            throw new AccountNotFound(accountId: $receiverAccountId);
        }

        $startCursor = $cursor ?? '0';
        $lastPagingToken = $startCursor;

        /**
         * @var array<ReceivedPayment> $receivedPayments
         */
        $receivedPayments = [];
        // one page will have max 100 entries.
        $nextPage = $sdk->transactions()->forAccount($receiverAccountId)->cursor($startCursor)->execute();
        while ($nextPage !== null && $nextPage->getTransactions()->count() !== 0) {
            $matches = self::extractReceivedPaymentsFromTxPage($nextPage, $receiverAccountId);
            if (count($matches) > 0) {
                $transactions = $nextPage->getTransactions()->toArray();
                $lastTransaction = end($transactions);
                if ($lastTransaction instanceof TransactionResponse) {
                    $lastPagingToken = $lastTransaction->getPagingToken();
                }
            }
            $receivedPayments = array_merge($receivedPayments, $matches);
            $nextPage = $nextPage->getNextPage();
        }

        return new ReceivedPaymentsQueryResult(
            cursor: $startCursor,
            receiverAccountId: $receiverAccountId,
            receivedPayments: $receivedPayments,
            lastTransactionPagingToken: $lastPagingToken,
        );
    }

    /**
     * Extracts the received payments from the given horizon transactions page response.
     *
     * @param TransactionsPageResponse $horizonTransactionsPage the page response to extract the received payments from.
     * @param string $receiverAccountId the id of the receiver account to extract the received payments for.
     *
     * @return array<ReceivedPayment> the found payments received by the receiver account.
     */
    private static function extractReceivedPaymentsFromTxPage(
        TransactionsPageResponse $horizonTransactionsPage,
        string $receiverAccountId,
    ): array {
        $horizonTransactions = $horizonTransactionsPage->getTransactions();
        /**
         * @var array<ReceivedPayment> $result
         */
        $result = [];

        foreach ($horizonTransactions as $horizonTransaction) {
            $matches = self::extractReceivedPaymentsFromTx($receiverAccountId, $horizonTransaction);
            $result = array_merge($result, $matches);
        }

        return $result;
    }

    /**
     * @return array<ReceivedPayment>
     */
    private static function extractReceivedPaymentsFromTx(
        string $receiverAccountId,
        TransactionResponse $fromHorizonTransaction,
    ): array {
        $txEnvelopeXdr = $fromHorizonTransaction->getEnvelopeXdr();
        $txResultXdr = $fromHorizonTransaction->getResultXdr();

        /**
         * @var array<ReceivedPayment> $result
         */
        $result = [];

        $txV1EnvelopeXdr = $txEnvelopeXdr->v1;

        /**
         * @var array<XdrOperation> $operations
         */
        $operations = [];

        /**
         * @var array<XdrOperationResult> $opResults
         */
        $opResults = [];

        $txFeeBumpEnvelopeXdr = $txEnvelopeXdr->feeBump;

        /**
         * @var string | null $sourceAccountId
         */
        $sourceAccountId = null;

        if ($txV1EnvelopeXdr !== null) {
            $operations = $txV1EnvelopeXdr->tx->operations;
            $opResults = $txResultXdr->result->results ?? [];
            $sourceAccount = $txV1EnvelopeXdr->tx->sourceAccount;
            $sourceAccountId = MuxedAccount::fromXdr($sourceAccount)->getAccountId();
        } elseif ($txFeeBumpEnvelopeXdr !== null) {
            $operations = $txFeeBumpEnvelopeXdr->tx->innerTx->v1->tx->operations;
            $innerResultPair = $txResultXdr->result->innerResultPair;
            if ($innerResultPair !== null) {
                $opResults = $innerResultPair->result->result->results ?? [];
            }
            $sourceAccount = $txFeeBumpEnvelopeXdr->tx->innerTx->v1->tx->sourceAccount;
            $sourceAccountId = MuxedAccount::fromXdr($sourceAccount)->getAccountId();
        }

        if (count($operations) !== count($opResults)) {
            return $result;
        }

        $index = 0;
        foreach ($operations as $operation) {
            $assetCode = 'native';
            /**
             * @var string|null $assetIssuer
             */
            $assetIssuer = null;
            /**
             * @var Asset | null $asset
             */
            $asset = null;
            /**
             * @var BigInteger | null $amount
             */
            $amount = null;

            /**
             * @var XdrMuxedAccount | null $destination
             */
            $destination = null;
            $paymentOp = $operation->getBody()->getPaymentOp();
            $pathPaymentStrictSendOp = $operation->getBody()->getPathPaymentStrictSendOp();
            $pathPaymentStrictReceiveOp = $operation->getBody()->getPathPaymentStrictReceiveOp();
            if ($paymentOp !== null) {
                $amount = $paymentOp->getAmount();
                $asset = Asset::fromXdr($paymentOp->getAsset());
                $destination = $paymentOp->getDestination();
            } elseif ($pathPaymentStrictSendOp !== null) {
                // since the dest amount is not specified in a strict-send op,
                // we need to get the dest amount from the operation's result
                $opResult = $opResults[$index];
                $success = $opResult->getResultTr()?->getPathPaymentStrictSendResult()?->getSuccess();
                $amount = $success?->getLast()->getAmount();
                $asset = Asset::fromXdr($pathPaymentStrictSendOp->getDestAsset());
                $destination = $pathPaymentStrictSendOp->getDestination();
            } elseif ($pathPaymentStrictReceiveOp !== null) {
                $amount = $pathPaymentStrictReceiveOp->getDestAmount();
                $asset = Asset::fromXdr($pathPaymentStrictReceiveOp->getDestAsset());
                $destination = $pathPaymentStrictReceiveOp->getDestination();
            }

            if ($asset instanceof AssetTypeCreditAlphanum) {
                $assetCode = $asset->getCode();
                $assetIssuer = $asset->getIssuer();
            }
            if ($amount !== null && $destination !== null && $sourceAccountId !== null) {
                $destAccountId = MuxedAccount::fromXdr($destination)->getAccountId();
                if ($destAccountId === $receiverAccountId) {
                    $opSourceAccountMuxed = $operation->getSourceAccount();
                    if ($opSourceAccountMuxed !== null) {
                        $sourceAccountId = MuxedAccount::fromXdr($opSourceAccountMuxed)->getAccountId();
                    }
                    $stellarAmount = new StellarAmount($amount);
                    $amountInAsDecimalString = $stellarAmount->getDecimalValueAsString();
                    $receivedPayment = new ReceivedPayment(
                        assetCode: $assetCode,
                        assetIssuer: $assetIssuer,
                        memoValue: $fromHorizonTransaction->getMemo()->valueAsString(),
                        memoType: $fromHorizonTransaction->getMemo()->typeAsString(),
                        amountIn: $amount,
                        amountInAsDecimalString: $amountInAsDecimalString,
                        senderAccountId: $sourceAccountId,
                        receiverAccountId: $destAccountId,
                        stellarTransactionId: $fromHorizonTransaction->getHash(),
                        transactionEnvelopeXdr: $txEnvelopeXdr->toBase64Xdr(),
                        transactionResultXdr: $txResultXdr->toBase64Xdr(),
                    );
                    $result[] = $receivedPayment;
                }
            }

            $index += 1;
        }

        return $result;
    }
}
