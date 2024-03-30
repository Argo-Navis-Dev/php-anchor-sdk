<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\QuoteNotFoundForId;
use ArgoNavis\PhpAnchorSdk\shared\Sep24AssetInfo;
use ArgoNavis\PhpAnchorSdk\shared\Sep38Quote;

/**
 * The interface for the sep-24 endpoints of the callback API.
 */
interface IInteractiveFlowIntegration
{
    /**
     * Returns all assets supported by the anchor.
     *
     * @return array<Sep24AssetInfo> the list of supported assets.
     */
    public function supportedAssets(): array;

    /**
     * Get the asset identified by `code` and `issuer`. If `issuer` is null, match only on `code`.
     *
     * @param string $code The asset code.
     * @param string|null $issuer The account ID of the issuer if any.
     *
     * @return ?Sep24AssetInfo an asset with the given code and issuer if found, otherwise null.
     */
    public function getAsset(string $code, ?string $issuer = null): ?Sep24AssetInfo;

    /**
     * Calculates and returns fee for the given parameter values.
     * This method is for complex fee calculation.
     * It is only called if the fee can not be calculated from the asset info data
     * (if feeFixed or feePercent are not provided).
     * Throws AnchorFailure if any error occurs.
     *
     * @param string $operation Kind of operation (deposit or withdraw).
     * @param string $assetCode Asset code.
     * @param float $amount Amount of the asset that will be deposited/withdrawn.
     * @param string|null $type (optional) Type of deposit or withdrawal (SEPA, bank_account, cash, etc...).
     *
     * @return float the calculated fee.
     *
     * @throws AnchorFailure if any error occurs or if not supported.
     */
    public function getFee(string $operation, string $assetCode, float $amount, ?string $type = null): float;

    /**
     * Creates a new SEP 24 withdrawal transaction.
     *
     * @param InteractiveWithdrawRequest $request the withdrawal request containing the prepared data for the new SEP 24 withdrawal transaction.
     *
     * @return InteractiveTransactionResponse The response containing the id of the created transaction and interactive url.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function withdraw(InteractiveWithdrawRequest $request): InteractiveTransactionResponse;

    /**
     * Creates a new SEP 24 deposit transaction.
     *
     * @param InteractiveDepositRequest $request the deposit request containing the prepared data for the new SEP 24 deposit transaction.
     *
     * @return InteractiveTransactionResponse The response containing the id of the created transaction and interactive url.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function deposit(InteractiveDepositRequest $request): InteractiveTransactionResponse;

    /**
     * Returns the SEP 24 transaction for the given id and user account if found.
     * Anchors must ensure that the transaction returned belongs to the Stellar account and optional memo value
     * used when making the original deposit or withdraw request that resulted in the transaction requested using this method.
     * If the given accountMemo is not null, the anchor must only return the transaction for the user identified by a combination of the account and memo.
     *
     * @param string $id id of the transaction.
     * @param string $accountId stellar account id or muxed account id of the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $accountMemo optional account memo identifying the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $lang Language code specified using RFC 4646.
     *
     * @return Sep24TransactionResponse|null The found transaction. Null if not found.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function findTransactionById(
        string $id,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep24TransactionResponse;

    /**
     * Returns the SEP 24 transaction for the given stellar transaction id and user account if found.
     * Anchors must ensure that the transaction returned belongs to the Stellar account and optional memo value
     * used when making the original deposit or withdraw request that resulted in the transaction requested using this method.
     * If the given accountMemo is not null, the anchor must only return the transaction for the user identified by a combination of the account and memo.
     *
     * @param string $stellarTransactionId stellar transaction id.
     * @param string $accountId stellar account id or muxed account id of the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $accountMemo optional account memo identifying the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $lang Language code specified using RFC 4646.
     *
     * @return Sep24TransactionResponse|null The found transaction. Null if not found.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function findTransactionByStellarTransactionId(
        string $stellarTransactionId,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep24TransactionResponse;

    /**
     * Returns the SEP 24 transaction for the given external transaction id and user account if found.
     * Anchors must ensure that the transaction returned belongs to the Stellar account and optional memo value
     * used when making the original deposit or withdraw request that resulted in the transaction requested using this method.
     * If the given accountMemo is not null, the anchor must only return the transaction for the user identified by a combination of the account and memo.
     *
     * @param string $externalTransactionId external transaction id.
     * @param string $accountId stellar account id or muxed account id of the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $accountMemo optional account memo identifying the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $lang Language code specified using RFC 4646.
     *
     * @return Sep24TransactionResponse|null The found transaction. Null if not found.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function findTransactionByExternalTransactionId(
        string $externalTransactionId,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep24TransactionResponse;

    /**
     * Returns the SEP 24 transaction history based on the search criteria given by the request and requesting user account.
     * Anchors must ensure that the transactions returned belong to the Stellar account and optional memo value
     * used when making the original deposit or withdraw requests that resulted in the transactions requested using this method.
     * If the given accountMemo is not null, the anchor must only return transactions for the user identified by a combination of the account and memo.
     *
     * @param Sep24TransactionHistoryRequest $request the request specifying the search criteria.
     * @param string $accountId stellar account id or muxed account id of the user requesting the transaction history (from SEP-10 jwt token).
     * @param string|null $accountMemo optional account memo identifying the user requesting the transaction history (from SEP-10 jwt token).
     *
     * @return array<Sep24TransactionResponse>|null the transactions found. null or empty if nothing found.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function getTransactionHistory(
        Sep24TransactionHistoryRequest $request,
        string $accountId,
        ?string $accountMemo = null,
    ): ?array;

    /**
     * Returns the SEP-38 Quote for the given id. If SEP-38 is not supported or no quote for the id was found
     * returns null.
     *
     * @param string $quoteId the id of the SEP-38 quote to return.
     * @param string $accountId account id of the user authenticated by SEP 10.
     * @param string|null $accountMemo (optional) account memo of the user authenticated by SEP 10.
     * If available it should be used together with the $accountId to identify the user.
     *
     * @return Sep38Quote the requested quote if SEP-38 is supported and the quote was found.
     *
     * @throws QuoteNotFoundForId if the quote could not be found for the given id.
     * @throws AnchorFailure if any other error occurs. E.g. SEP-38 is not supported.
     */
    public function getQuoteById(string $quoteId, string $accountId, ?string $accountMemo = null): Sep38Quote;
}
