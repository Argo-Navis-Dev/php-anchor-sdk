<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

use ArgoNavis\PhpAnchorSdk\Sep08\Sep08ResponseStatus;

/**
 * This response means that the user must complete an action before this transaction can be approved.
 * The approval service will provide a URL that facilitates the action.
 * Upon completion, the user will resubmit the transaction.
 */
class ApprovalActionRequired
{
    /**
     * @var string $message A human-readable string containing information regarding the action required.
     */
    public string $message;
    /**
     * @var string $actionUrl A URL that allows the user to complete the actions required to have the transaction approved.
     */
    public string $actionUrl;

    /**
     * @var string|null $actionMethod (optional) GET or POST, indicating the type of request that should be made
     * to the action_url. If not provided, GET is assumed.
     */
    public ?string $actionMethod = null;
    /**
     * @var array<string>|null $actionFields (optional) An array of additional fields defined by
     * SEP-9 Standard KYC / AML fields that the client may optionally provide to the approval service when sending
     * the request to the action_url to circumvent the need for the user to enter the information manually.
     */
    public ?array $actionFields = null;

    /**
     * Constructor.
     *
     * @param string $message A human-readable string containing information regarding the action required.
     * @param string $actionUrl A URL that allows the user to complete the actions required to have the
     * transaction approved.
     * @param string|null $actionMethod (optional) GET or POST, indicating the type of request that should be made
     *  to the action_url. If not provided, GET is assumed.
     * @param string[]|null $actionFields (optional) An array of additional fields defined by
     *  SEP-9 Standard KYC / AML fields that the client may optionally provide to the approval service when sending
     *  the request to the action_url to circumvent the need for the user to enter the information manually.
     */
    public function __construct(
        string $message,
        string $actionUrl,
        ?string $actionMethod = null,
        ?array $actionFields = null,
    ) {
        $this->message = $message;
        $this->actionUrl = $actionUrl;
        $this->actionMethod = $actionMethod;
        $this->actionFields = $actionFields;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $json
         */
        $json = [
            'status' => Sep08ResponseStatus::ACTION_REQUIRED,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
        ];

        if ($this->actionMethod !== null) {
            $json += ['action_method' => $this->actionMethod];
        } else {
            $json += ['action_method' => 'GET'];
        }

        if ($this->actionFields !== null) {
            $json += ['action_fields' => $this->actionFields];
        }

        return $json;
    }
}
