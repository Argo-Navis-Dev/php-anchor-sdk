<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep31TransactionStatus
{
    /**
     * @const PENDING_SENDER -- -- awaiting payment to be sent by Sending Anchor
     */
    public const PENDING_SENDER = 'pending_sender';

    /**
     * @const PENDING_STELLAR -- transaction has been submitted to Stellar network, but is not yet confirmed.
     */
    public const PENDING_STELLAR = 'pending_stellar';

    /**
     * @const PENDING_CUSTOMER_INFO_UPDATE -- certain pieces of information need to be updated by the Sending Anchor
     */
    public const PENDING_CUSTOMER_INFO_UPDATE = 'pending_customer_info_update';

    /**
     * @const PENDING_TRANSACTION_INFO_UPDATE -- certain pieces of information need to be updated by the Sending Anchor
     */
    public const PENDING_TRANSACTION_INFO_UPDATE = 'pending_transaction_info_update';

    /**
     * @const PENDING_RECEIVER --- payment is being processed by the Receiving Anchor.
     */
    public const PENDING_RECEIVER = 'pending_receiver ';

    /**
     * @const PENDING_EXTERNAL -- payment has been submitted to external network, but is not yet confirmed.
     */
    public const PENDING_EXTERNAL = 'pending_external';

    /**
     * @const COMPLETED -- funds have been delivered to the Receiving Client.
     */
    public const COMPLETED = 'completed';

    /**
     * @const REFUNDED -- funds have been refunded to the Sending Anchor.
     */
    public const REFUNDED = 'refunded';

    /**
     * @const EXPIRED -- funds were never received by the anchor and the transaction is considered abandoned by the user.
     * If a SEPte was specified when the transaction was initiated, the transaction should expire when the quote expires, otherwise anchoresponsible for determining when transactions are considered expired.
     */
    public const EXPIRED = 'expired';

    /**
     * @const ERROR -- catch-all for any error not enumerated above.
     */
    public const ERROR = 'error';
}
