<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep10;

use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;

use function is_string;

class ValidationRequest
{
    /**
     * The signed transaction.
     *
     * @var string xdr encoded transaction envelope (base64).
     */
    public string $transaction;

    /**
     * @var string The url of the request. This is needed for the jwt token.
     */
    public string $url;

    public function __construct(string $url, string $transaction)
    {
        $this->url = $url;
        $this->transaction = $transaction;
    }

    /**
     * Creates a ValidationRequest object from the given json array.
     *
     * @param string $url the url of the request.
     * @param array<array-key, mixed> $data the array to parse the data from.
     *
     * @return ValidationRequest the parsed validation request.
     *
     * @throws InvalidRequestData
     */
    public static function fromDataArray(string $url, array $data): ValidationRequest
    {
        if (!isset($data['transaction'])) {
            throw new InvalidRequestData('Transaction is not set');
        }
        $transaction = $data['transaction'];
        if (!is_string($transaction)) {
            throw new InvalidRequestData('Invalid transaction. Must be string.');
        }

        return new ValidationRequest($url, $transaction);
    }
}
