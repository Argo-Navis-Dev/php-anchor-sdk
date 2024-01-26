<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep12;

use ArgoNavis\PhpAnchorSdk\callback\GetCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerVerificationRequest;

class Sep12CustomerRequestBase
{
    /**
     * @var string|null (optional) The ID of the customer as returned in the response of a previous PUT request.
     * If the customer has not been registered, they do not yet have an id.
     */
    public ?string $id = null;

    /**
     * @var string|null (deprecated, optional) The server should infer the account from the sub value in the SEP-10 JWT to identify the customer.
     * The account parameter is only used for backwards compatibility, and if explicitly provided in the request body it should match the sub value of the decoded SEP-10 JWT.
     */
    public ?string $account = null;

    /**
     * @var string|null (optional) the client-generated memo that uniquely identifies the customer. If a memo is present in the decoded SEP-10 JWT's sub value,
     * it must match this parameter value. If a muxed account is used as the JWT's sub value, memos sent in requests must match the 64-bit integer subaccount ID of the muxed account.
     * See <a href:"https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#shared-omnibus-or-pooled-accounts">Shared Accounts</a> for more information.
     */
    public ?string $memo = null;

    /**
     * @var string|null (deprecated, optional) type of memo. One of text, id or hash. Deprecated because memos should always be of type id,
     * although anchors should continue to support this parameter for outdated clients. If hash, memo should be base64-encoded.
     * If a memo is present in the decoded SEP-10 JWT's sub value, this parameter can be ignored.
     * See <a href:"https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#shared-omnibus-or-pooled-accounts">Shared Accounts</a> for more information.
     */
    public ?string $memoType = null;

    /**
     * @var string|null $type (optional) the type of action the customer is being KYCd for.
     * See <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#type-specification">Type Specification</a>.
     */
    public ?string $type = null;

    public function __construct(
        ?string $id = null,
        ?string $account = null,
        ?string $memo = null,
        ?string $memoType = null,
        ?string $type = null,
    ) {
        $this->id = $id;
        $this->account = $account;
        $this->memo = $memo;
        $this->memoType = $memoType;
        $this->type = $type;
    }

    public static function fromGetCustomerRequest(GetCustomerRequest $request): Sep12CustomerRequestBase
    {
        return new Sep12CustomerRequestBase(
            $request->id,
            $request->account,
            $request->memo,
            $request->memoType,
            $request->type,
        );
    }

    public static function fromPutCustomerRequest(PutCustomerRequest $request): Sep12CustomerRequestBase
    {
        return new Sep12CustomerRequestBase(
            $request->id,
            $request->account,
            $request->memo,
            $request->memoType,
            $request->type,
        );
    }

    public static function fromPutCustomerVerificationRequest(
        PutCustomerVerificationRequest $request,
    ): Sep12CustomerRequestBase {
        return new Sep12CustomerRequestBase($request->id);
    }
}
