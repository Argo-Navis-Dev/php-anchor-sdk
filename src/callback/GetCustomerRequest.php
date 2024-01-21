<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

/**
 * The request body of GET /customer endpoint.
 *
 * See: <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#customer-get">Customer GET</a>.
 */
class GetCustomerRequest
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
     * @var string|null (optional) the type of action the customer is being KYCd for.
     * See <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#type-specification">Type Specification</a>.
     */
    public ?string $type = null;

    /**
     * @var string|null (optional) Defaults to en. Language code specified using ISO 639-1.
     * Human-readable descriptions, choices, and messages should be in this language.
     */
    public ?string $lang = 'en';

    /**
     * @param string|null $id (optional) The ID of the customer as returned in the response of a previous PUT request.
     *  If the customer has not been registered, they do not yet have an id.
     * @param string|null $account (deprecated, optional) The server should infer the account from the sub value in the SEP-10 JWT to identify the customer.
     *  The account parameter is only used for backwards compatibility, and if explicitly provided in the request body it should match the sub value of the decoded SEP-10 JWT.
     * @param string|null $memo (optional) the client-generated memo that uniquely identifies the customer. If a memo is present in the decoded SEP-10 JWT's sub value,
     *  it must match this parameter value. If a muxed account is used as the JWT's sub value, memos sent in requests must match the 64-bit integer subaccount ID of the muxed account.
     *  See <a href:"https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#shared-omnibus-or-pooled-accounts">Shared Accounts</a> for more information.
     * @param string|null $memoType (deprecated, optional) type of memo. One of text, id or hash. Deprecated because memos should always be of type id,
     *  although anchors should continue to support this parameter for outdated clients. If hash, memo should be base64-encoded.
     *  If a memo is present in the decoded SEP-10 JWT's sub value, this parameter can be ignored.
     *  See <a href:"https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#shared-omnibus-or-pooled-accounts">Shared Accounts</a> for more information.
     * @param string|null $type (optional) the type of action the customer is being KYCd for.
     *  See <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#type-specification">Type Specification</a>.
     * @param string|null $lang (optional) Defaults to en. Language code specified using ISO 639-1.
     *  Human-readable descriptions, choices, and messages should be in this language.
     */
    public function __construct(
        ?string $id = null,
        ?string $account = null,
        ?string $memo = null,
        ?string $memoType = null,
        ?string $type = null,
        ?string $lang = 'en',
    ) {
        $this->id = $id;
        $this->account = $account;
        $this->memo = $memo;
        $this->memoType = $memoType;
        $this->type = $type;
        $this->lang = $lang;
    }
}
