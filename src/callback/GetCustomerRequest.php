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
     * @param string $account The account id of the customer from the jwt token. If id is not null, the anchor should check if the account id
     *   matches to the customer fetched for the given id. Otherwise, it should throw SepNotAuthorized.
     * @param int|null $memo (optional) the memo from the jwt token if any. If id is not null, the anchor should check if the memo
     *   matches to the customer fetched fo the given id. Otherwise, it should throw SepNotAuthorized.
     * @param string|null $id (optional) The ID of the customer as returned in the response of a previous PUT request.
     *  If the customer has not been registered, they do not yet have an id.
     *  See <a href:"https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#shared-omnibus-or-pooled-accounts">Shared Accounts</a> for more information.
     * @param string|null $type (optional) the type of action the customer is being KYCd for.
     *  See <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#type-specification">Type Specification</a>.
     * @param string|null $lang (optional) Defaults to en. Language code specified using ISO 639-1.
     *  Human-readable descriptions, choices, and messages should be in this language.
     */
    public function __construct(
        string $account,
        ?int $memo = null,
        ?string $id = null,
        ?string $type = null,
        ?string $lang = 'en',
    ) {
        $this->account = $account;
        $this->id = $id;
        $this->memo = $memo;
        $this->type = $type;
        $this->lang = $lang;
    }
}
