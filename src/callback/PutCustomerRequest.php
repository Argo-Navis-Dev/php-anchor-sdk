<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use Psr\Http\Message\UploadedFileInterface;

/**
 * The request body of PUT /customer endpoint.
 *
 * See: <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#customer-put">Customer PUT</a>.
 */
class PutCustomerRequest
{
    /**
     * @var string|null (optional) The id value returned from a previous call to this endpoint. If specified, no other parameter is required.
     */
    public ?string $id = null;

    /**
     * @var string|null (deprecated, optional) The server should infer the account from the sub value in the SEP-10 JWT to identify the customer.
     */
    public ?string $account = null;

    /**
     * @var string|null (optional) the client-generated memo that uniquely identifies the customer. If a memo is present in the decoded SEP-10 JWT's sub value,
     *  it must match this parameter value. If a muxed account is used as the JWT's sub value, memos sent in requests must match the 64-bit integer subaccount ID of the muxed account.
     *  See <a href:"https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#shared-omnibus-or-pooled-accounts">Shared Accounts</a> for more information.
     */
    public ?string $memo = null;

    /**
     * @var string|null (deprecated, optional) type of memo. One of text, id or hash. Deprecated because memos should always be of type id, although anchors should
     *  continue to support this parameter for outdated clients. If hash, memo should be base64-encoded. If a memo is present in the decoded SEP-10 JWT's sub value,
     *  this parameter can be ignored. See <a href:"https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#shared-omnibus-or-pooled-accounts">Shared Accounts</a> for more information.
     */
    public ?string $memoType = null;

    /**
     * @var string|null (optional) The type of the customer as defined in the <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#type-specification">Type Specification</a>.
     */
    public ?string $type = null;

    /**
     * @var array<array-key, mixed>|null The client should also transmit one or more of the fields listed in
     *  <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>, depending on what the anchor has indicated it needs.
     */
    public ?array $kycFields = null;

    /**
     * @var array<array-key, UploadedFileInterface>|null Uploaded files by the client. Listed in
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>,
     * depending on what the anchor has indicated it needs. This variable contains upload metadata in a normalized tree,
     * with each leaf an instance of Psr\Http\Message\UploadedFileInterface.
     */
    public ?array $kycUploadedFiles = null;

    /**
     * @param string|null $id (optional) The id value returned from a previous call to this endpoint. If specified, no other parameter is required.
     * @param string|null $account (deprecated, optional) The server should infer the account from the sub value in the SEP-10 JWT to identify the customer.
     * The account parameter is only used for backwards compatibility, and if explicitly provided in the request body it should match the sub value of the decoded SEP-10 JWT.
     * @param string|null $memo (optional) the client-generated memo that uniquely identifies the customer. If a memo is present in the decoded SEP-10 JWT's sub value,
     * it must match this parameter value. If a muxed account is used as the JWT's sub value, memos sent in requests must match the 64-bit integer subaccount ID of the muxed account.
     * See <a href:"https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#shared-omnibus-or-pooled-accounts">Shared Accounts</a> for more information.
     * @param string|null $memoType (deprecated, optional) type of memo. One of text, id or hash. Deprecated because memos should always be of type id, although anchors should
     * continue to support this parameter for outdated clients. If hash, memo should be base64-encoded. If a memo is present in the decoded SEP-10 JWT's sub value,
     * this parameter can be ignored. See <a href:"https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#shared-omnibus-or-pooled-accounts">Shared Accounts</a> for more information.
     * @param string|null $type (optional) The type of the customer as defined in the <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#type-specification">Type Specification</a>.
     * @param array<array-key, mixed>|null $kycFields The client should also transmit one or more of the fields listed in
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>, depending on what the anchor has indicated it needs.
     * @param array<array-key, mixed>|null $kycUploadedFiles Uploaded files by the client. Listed in
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>,
     * depending on what the anchor has indicated it needs. This variable contains upload metadata in a normalized tree,
     * with each leaf an instance of Psr\Http\Message\UploadedFileInterface.
     */
    public function __construct(
        ?string $id = null,
        ?string $account = null,
        ?string $memo = null,
        ?string $memoType = null,
        ?string $type = null,
        ?array $kycFields = null,
        ?array $kycUploadedFiles = null,
    ) {
        $this->id = $id;
        $this->account = $account;
        $this->memo = $memo;
        $this->memoType = $memoType;
        $this->type = $type;
        $this->kycFields = $kycFields;
        $this->kycUploadedFiles = $kycUploadedFiles;
    }
}
