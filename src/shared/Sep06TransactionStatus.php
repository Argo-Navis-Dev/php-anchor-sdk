<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep06TransactionStatus
{
    /**
     * @const COMPLETED -- deposit/withdrawal fully completed.
     */
    public const COMPLETED = 'completed';

    /**
     * @const PENDING_EXTERNAL -- deposit/withdrawal has been submitted to external network, but is not yet confirmed.
     * This is the status when waiting on Bitcoin or other external crypto network to complete a transaction,
     * or when waiting on a bank transfer.
     */
    public const PENDING_EXTERNAL = 'pending_external';

    /**
     * @const PENDING_ANCHOR -- deposit/withdrawal is being processed internally by anchor.
     */
    public const PENDING_ANCHOR = 'pending_anchor';

    /**
     * @const PENDING_ANCHOR -- deposit/withdrawal operation has been submitted to Stellar network, but is not yet confirmed.
     */
    public const PENDING_STELLAR = 'pending_stellar';

    /**
     * @const PENDING_TRUST -- the user must add a trust-line for the asset for the deposit to complete.
     */
    public const PENDING_TRUST = 'pending_trust';

    /**
     * @const PENDING_USER -- the user must take additional action before the deposit / withdrawal can complete.
     */
    public const PENDING_USER = 'pending_user';

    /**
     * @const PENDING_USER_TRANSFER_START -- the user has not yet initiated their transfer to the anchor.
     * This is the necessary first step in any deposit or withdrawal flow.
     */
    public const PENDING_USER_TRANSFER_START = 'pending_user_transfer_start';

    /**
     * @const PENDING_USER_TRANSFER_START -- means the Stellar payment has been successfully received by the anchor
     * and the off-chain funds are available for the customer to pick up. Only used for withdrawal transactions.
     */
    public const PENDING_USER_TRANSFER_COMPLETE = 'pending_user_transfer_complete';

    /**
     * @const PENDING_CUSTOMER_INFO_UPDATE -- certain pieces of information need to be updated by the user.
     * See the pending customer info update [SEP-06 section](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0006.md#pending-customer-info-update).
     */
    public const PENDING_CUSTOMER_INFO_UPDATE = 'pending_customer_info_update';

    /**
     * @const PENDING_CUSTOMER_INFO_UPDATE -- certain pieces of information need to be updated by the user.
     * See the pending transaction info update [SEP-06 section](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0006.md#pending-transaction-info-update).
     */
    public const PENDING_TRANSACTION_INFO_UPDATE = 'pending_transaction_info_update';

    /**
     * @const INCOMPLETE -- there is not yet enough information for this transaction to be initiated.
     * Perhaps the user has not yet entered necessary info in an interactive flow.
     */
    public const INCOMPLETE = 'incomplete';

    /**
     * @const EXPIRED -- funds were never received by the anchor and the transaction is considered abandoned by the user
     * If a SEP-38 quote was specified when the transaction was initiated, the transaction should expire when the
     * quote expires, otherwise anchors are responsible for determining when transactions are considered expired.
     */
    public const EXPIRED = 'expired';

    /**
     * @const NO_MARKET -- could not complete deposit because no satisfactory asset/XLM market was available
     * to create the account.
     */
    public const NO_MARKET = 'no_market';

    /**
     * @const TOO_SMALL -- deposit/withdrawal size less than min_amount.
     */
    public const TOO_SMALL = 'too_small';

    /**
     * @const TOO_LARGE -- deposit/withdrawal size exceeded max_amount.
     */
    public const TOO_LARGE = 'too_large';

    /**
     * @const ERROR -- catch-all for any error not enumerated above.
     */
    public const ERROR = 'error';

    /**
     * @const REFUNDED -- the deposit/withdrawal is fully refunded.
     */
    public const REFUNDED = 'refunded';
}
