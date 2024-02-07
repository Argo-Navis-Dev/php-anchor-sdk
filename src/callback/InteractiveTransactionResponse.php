<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\callback;

class InteractiveTransactionResponse
{
    /**
     * @var string $type Always set to interactive_customer_info_needed.
     */
    public string $type;

    /**
     * @var string $url URL hosted by the anchor. The wallet should show this URL to the user as a popup.
     */
    public string $url;

    /**
     * @var string $id The anchor's internal ID for this deposit / withdrawal request. The wallet will use this ID to query the /transaction endpoint to check status of the request.
     */
    public string $id;

    /**
     * @param string $type Always set to interactive_customer_info_needed.
     * @param string $url URL hosted by the anchor. The wallet should show this URL to the user as a popup.
     * @param string $id The anchor's internal ID for this deposit / withdrawal request. The wallet will use this ID to query the /transaction endpoint to check status of the request.
     */
    public function __construct(string $type, string $url, string $id)
    {
        $this->type = $type;
        $this->url = $url;
        $this->id = $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'type' => $this->type,
            'url' => $this->url,
            'id' => $this->id,
        ];
    }
}
