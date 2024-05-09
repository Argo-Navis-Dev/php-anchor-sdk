<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\exception\AnchorFailure;
use ArgoNavis\PhpAnchorSdk\exception\Sep31TransactionCallbackNotSupported;
use ArgoNavis\PhpAnchorSdk\exception\Sep31TransactionNotFoundForId;
use ArgoNavis\PhpAnchorSdk\shared\Sep31AssetInfo;

/**
 * The interface for the cross-border payments business logic for the Receiver side. See SEP-31.
 */
interface ICrossBorderIntegration
{
    /**
     * Returns the supported assets.
     *
     * @param string $accountId account id of the sending anchor received via sep-10
     * @param string|null $accountMemo account memo of the sending anchor received via sep-10.
     * @param string|null $lang (optional) Defaults to en. Language code specified using ISO 639-1.
     *
     * @return array<Sep31AssetInfo> The supported assets.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function supportedAssets(string $accountId, ?string $accountMemo = null, ?string $lang = null): array;

    /**
     * Posts a transaction. This request initiates a payment.
     *
     * @param Sep31PostTransactionRequest $request The request data.
     *
     * @return Sep31PostTransactionResponse The response data.
     *
     * @throws AnchorFailure if any error occurs.
     */
    public function postTransaction(Sep31PostTransactionRequest $request): Sep31PostTransactionResponse;

    /**
     * Returns the transaction identified by $id.
     *
     * @param string $id The id of the transaction.
     * @param string $accountId The account id of the customer from the jwt token
     * @param string|null $accountMemo the memo from the jwt token if any
     *
     * @return Sep31TransactionResponse The transaction if found.
     *
     * @throws Sep31TransactionNotFoundForId if the transaction given by could not be found.
     * @throws AnchorFailure if any error occurs.
     */
    public function getTransactionById(
        string $id,
        string $accountId,
        ?string $accountMemo = null,
    ): Sep31TransactionResponse;

    /**
     * This endpoint can be used by the Sending Anchor to register a callback URL that the Receiving Anchor will
     * make application/json POST requests to containing the transaction object defined in the
     * response to GET /transaction/:id whenever the transaction's status value has changed.
     *
     * @param Sep31PutTransactionCallbackRequest $request request data.
     *
     * @throws Sep31TransactionCallbackNotSupported if the endpoint is not supported by the anchor.
     * @throws AnchorFailure if any other error occurs.
     */
    public function putTransactionCallback(Sep31PutTransactionCallbackRequest $request): void;
}
