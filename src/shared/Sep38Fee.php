<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep38Fee
{
    /**
     * @var string $total The total amount of fee applied.
     */
    public string $total;

    /**
     * @var IdentificationFormatAsset $asset The asset in which the fee is applied, represented through
     * the Asset Identification Format.
     */
    public IdentificationFormatAsset $asset;

    /**
     * @var array<Sep38FeeDetails>|null $details (optional) An array of objects detailing the fees that were used to
     * calculate the conversion price. This can be used to detail the price components for the end-user.
     */
    public ?array $details = null;

    /**
     * @param string $total The total amount of fee applied.
     * @param IdentificationFormatAsset $asset The asset in which the fee is applied, represented through
     *  the Asset Identification Format.
     * @param array<Sep38FeeDetails>|null $details (optional) An array of objects detailing the fees that were used to
     *  calculate the conversion price. This can be used to detail the price components for the end-user.
     */
    public function __construct(string $total, IdentificationFormatAsset $asset, ?array $details = null)
    {
        $this->total = $total;
        $this->asset = $asset;
        $this->details = $details;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $result
         */
        $result = [
            'total' => $this->total,
            'asset' => $this->asset->getStringRepresentation(),
        ];

        if ($this->details !== null) {
            $feeDetails = [];
            foreach ($this->details as $detail) {
                $feeDetails[] = $detail->toJson();
            }
            $result['details'] = $feeDetails;
        }

        return $result;
    }
}
