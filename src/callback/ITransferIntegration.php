<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\shared\Sep06AssetInfo;

/**
 * The interface for the sep-06 endpoints of the callback API.
 */
interface ITransferIntegration
{
    /**
     * Returns all assets supported by the anchor.
     *
     * @return array<Sep06AssetInfo> the list of supported assets.
     */
    public function supportedAssets(): array;

    /**
     * User request to initiate a deposit operation.
     *
     * @param StartDepositRequest $request the validated request data.
     *
     * @return StartDepositResponse The information needed by the user to initiate a deposit.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function deposit(StartDepositRequest $request): StartDepositResponse;

    /**
     * User request to initiate a deposit exchange operation.
     *
     * @param StartDepositExchangeRequest $request the validated request data.
     *
     * @return StartDepositResponse The information needed by the user to initiate a deposit.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function depositExchange(StartDepositExchangeRequest $request): StartDepositResponse;

    /**
     * User request to initiate a withdrawal operation.
     *
     * @param StartWithdrawRequest $request the validated request data.
     *
     * @return StartWithdrawResponse The information needed by the user to initiate a withdrawal.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function withdraw(StartWithdrawRequest $request): StartWithdrawResponse;

    /**
     * User request to initiate a withdrawal exchange operation.
     *
     * @param StartWithdrawExchangeRequest $request the validated request data.
     *
     * @return StartWithdrawResponse The information needed by the user to initiate a withdrawal.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function withdrawExchange(StartWithdrawExchangeRequest $request): StartWithdrawResponse;

    /**
     * Returns the SEP 06 transaction for the given id and user account if found.
     * Anchors must ensure that the transaction returned belongs to the Stellar account and optional memo value
     * used when making the original deposit or withdraw request that resulted in the transaction requested using this method.
     * If the given accountMemo is not null, the anchor must only return the transaction for the user identified by a combination of the account and memo.
     *
     * @param string $id id of the transaction.
     * @param string $accountId stellar account id or muxed account id of the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $accountMemo optional account memo identifying the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $lang Language code specified using RFC 4646.
     *
     * @return Sep06TransactionResponse|null The found transaction. Null if not found.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function findTransactionById(
        string $id,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep06TransactionResponse;

    /**
     * Returns the SEP 06 transaction for the given stellar transaction id and user account if found.
     * Anchors must ensure that the transaction returned belongs to the Stellar account and optional memo value
     * used when making the original deposit or withdraw request that resulted in the transaction requested using this method.
     * If the given accountMemo is not null, the anchor must only return the transaction for the user identified by a combination of the account and memo.
     *
     * @param string $stellarTransactionId stellar transaction id.
     * @param string $accountId stellar account id or muxed account id of the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $accountMemo optional account memo identifying the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $lang Language code specified using RFC 4646.
     *
     * @return Sep06TransactionResponse|null The found transaction. Null if not found.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function findTransactionByStellarTransactionId(
        string $stellarTransactionId,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep06TransactionResponse;

    /**
     * Returns the SEP 06 transaction for the given external transaction id and user account if found.
     * Anchors must ensure that the transaction returned belongs to the Stellar account and optional memo value
     * used when making the original deposit or withdraw request that resulted in the transaction requested using this method.
     * If the given accountMemo is not null, the anchor must only return the transaction for the user identified by a combination of the account and memo.
     *
     * @param string $externalTransactionId external transaction id.
     * @param string $accountId stellar account id or muxed account id of the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $accountMemo optional account memo identifying the user requesting the transaction (from SEP-10 jwt token).
     * @param string|null $lang Language code specified using RFC 4646.
     *
     * @return Sep06TransactionResponse|null The found transaction. Null if not found.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function findTransactionByExternalTransactionId(
        string $externalTransactionId,
        string $accountId,
        ?string $accountMemo = null,
        ?string $lang = null,
    ): ?Sep06TransactionResponse;

    /**
     * Returns the SEP 06 transaction history based on the search criteria given by the request and requesting user account.
     * Anchors must ensure that the transactions returned belong to the Stellar account and optional memo value
     * used when making the original deposit or withdraw requests that resulted in the transactions requested using this method.
     * If the given accountMemo is not null, the anchor must only return transactions for the user identified by a combination of the account and memo.
     *
     * @param TransactionHistoryRequest $request the request specifying the search criteria.
     * @param string $accountId stellar account id or muxed account id of the user requesting the transaction history (from SEP-10 jwt token).
     * @param string|null $accountMemo optional account memo identifying the user requesting the transaction history (from SEP-10 jwt token).
     *
     * @return array<Sep06TransactionResponse>|null the transactions found. null or empty if nothing found.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function getTransactionHistory(
        TransactionHistoryRequest $request,
        string $accountId,
        ?string $accountMemo = null,
    ): ?array;
}
