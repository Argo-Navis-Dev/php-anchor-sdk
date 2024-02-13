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
     * @var string The account id of the customer from the jwt token. If id is not null, the anchor should check if the account id
     * matches to the customer fetched for the given id. Otherwise, it should throw SepNotAuthorized.
     */
    public string $account;

    /**
     * @var int|null (optional) the memo from the jwt token if any. If id is not null, the anchor should check if the memo
     * matches to the customer fetched for the given id. Otherwise, it should throw SepNotAuthorized.
     */
    public ?int $memo = null;

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
     * @param string $account The account id of the customer from the jwt token. If id is not null, the anchor should check if the account id
     *  matches to the customer fetched for the given id. Otherwise, it should throw SepNotAuthorized.
     * @param int|null $memo (optional) the memo from the jwt token if any. If id is not null, the anchor should check if the memo
     *  matches to the customer fetched for the given id. Otherwise, it should throw SepNotAuthorized.
     * @param string|null $id (optional) The id value returned from a previous call to this endpoint. If specified, no other parameter is required.
     * @param string|null $type (optional) The type of the customer as defined in the <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#type-specification">Type Specification</a>.
     * @param array<array-key, mixed>|null $kycFields The client should also transmit one or more of the fields listed in
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>, depending on what the anchor has indicated it needs.
     * @param array<array-key, UploadedFileInterface>|null $kycUploadedFiles Uploaded files by the client. Listed in
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0009.md">SEP-009</a>,
     * depending on what the anchor has indicated it needs. This variable contains upload metadata in a normalized tree,
     * with each leaf an instance of Psr\Http\Message\UploadedFileInterface.
     */
    public function __construct(
        string $account,
        ?int $memo = null,
        ?string $id = null,
        ?string $type = null,
        ?array $kycFields = null,
        ?array $kycUploadedFiles = null,
    ) {
        $this->account = $account;
        $this->memo = $memo;
        $this->id = $id;
        $this->type = $type;
        $this->kycFields = $kycFields;
        $this->kycUploadedFiles = $kycUploadedFiles;
    }
}
