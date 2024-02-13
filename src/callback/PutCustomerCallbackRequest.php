<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

class PutCustomerCallbackRequest
{
    /**
     * @var string|null $id (optional) The id of the customer to put the callback url for if given by client request.
     */
    public ?string $id = null;

    /**
     * @var string $account The stellar account id of the customer from the jwt token. If id is not null, the anchor should check if the account id
     * matches to the customer fetched for the given id. Otherwise, it should throw SepNotAuthorized.
     */
    public string $account;

    /**
     * @var int|null $memo (optional) the memo from the jwt token if any. If id is not null, the anchor should check if the memo
     * matches to the customer fetched for the given id. Otherwise, it should throw SepNotAuthorized.
     */
    public ?int $memo = null;

    /**
     * @var string|null $url the url to set as a callback url for the customer. if null, the currently set url should be deleted.
     */
    public ?string $url = null;

    /**
     * Constructor.
     *
     * @param string $account The stellar account id of the customer from the jwt token. If id is not null, the anchor should check if the account id
     *  matches to the customer fetched for the given id. Otherwise, it should throw SepNotAuthorized.
     * @param int|null $memo (optional) the memo from the jwt token if any. If id is not null, the anchor should check if the memo
     *   matches to the customer fetched for the given id. Otherwise, it should throw SepNotAuthorized.
     * @param string|null $id (optional) The id of the customer to put the callback url for if given by client request.
     * @param string|null $url The url to set as a callback url for the customer. if null, the currently set url should be deleted.
     */
    public function __construct(string $account, ?int $memo, ?string $id, ?string $url)
    {
        $this->id = $id;
        $this->account = $account;
        $this->memo = $memo;
        $this->url = $url;
    }
}
