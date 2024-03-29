<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

class Sep38AssetInfo
{
    /**
     * @var IdentificationFormatAsset $asset The Asset Identification Format value.
     */
    public IdentificationFormatAsset $asset;
    /**
     * @var array<Sep38DeliveryMethod>|null $sellDeliveryMethods (optional) Only for non-Stellar assets.
     * An array of objects describing the methods a client can use to sell/deliver funds to the anchor. The method of
     * delivery may affect the expiration and/or price provided in a POST /quote response. If the delivery method is
     * not necessary for providing accurate quotes and expirations, the server can omit this attribute.
     */
    public ?array $sellDeliveryMethods = null;

    /**
     * @var array<Sep38DeliveryMethod>|null $buyDeliveryMethods (optional) Only for non-Stellar assets. An array of
     * objects describing the methods a client can use to buy/retrieve funds from the anchor. The method of delivery
     * may affect the expiration and/or price provided in a POST /quote response. If the delivery method is not
     * necessary for providing accurate quotes and expirations, the server can omit this attribute.
     */
    public ?array $buyDeliveryMethods = null;

    /**
     * @var array<string>|null $countryCodes (optional) Only for fiat assets. A list of ISO 3166-2 codes of the
     * countries where the Anchor operates for fiat transactions. Anchor may not require second part of the
     * ISO 3166-2 to be passed (i.e. use ISO-3166-1 alpha-2 instead).
     */
    public ?array $countryCodes = null;

    /**
     * @param IdentificationFormatAsset $asset The Asset Identification Format value.
     * @param array<Sep38DeliveryMethod>|null $sellDeliveryMethods (optional) Only for non-Stellar assets.
     *  An array of objects describing the methods a client can use to sell/deliver funds to the anchor. The method of
     *  delivery may affect the expiration and/or price provided in a POST /quote response. If the delivery method is
     *  not necessary for providing accurate quotes and expirations, the server can omit this attribute.
     * @param array<Sep38DeliveryMethod>|null $buyDeliveryMethods (optional) Only for non-Stellar assets. An array of
     *  objects describing the methods a client can use to buy/retrieve funds from the anchor. The method of delivery
     *  may affect the expiration and/or price provided in a POST /quote response. If the delivery method is not
     *  necessary for providing accurate quotes and expirations, the server can omit this attribute.
     * @param array<string>|null $countryCodes (optional) Only for fiat assets. A list of ISO 3166-2 codes of the
     *  countries where the Anchor operates for fiat transactions. Anchor may not require second part of the
     *  ISO 3166-2 to be passed (i.e. use ISO-3166-1 alpha-2 instead).
     */
    public function __construct(
        IdentificationFormatAsset $asset,
        ?array $sellDeliveryMethods = null,
        ?array $buyDeliveryMethods = null,
        ?array $countryCodes = null,
    ) {
        $this->asset = $asset;
        $this->sellDeliveryMethods = $sellDeliveryMethods;
        $this->buyDeliveryMethods = $buyDeliveryMethods;
        $this->countryCodes = $countryCodes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        /**
         * @var array<string, mixed> $result
         */
        $result = ['asset' => $this->asset->getStringRepresentation()];
        if ($this->sellDeliveryMethods !== null) {
            $sellDeliveryMethods = [];
            foreach ($this->sellDeliveryMethods as $method) {
                $sellDeliveryMethods[] = $method->toJson();
            }
            $result += ['sell_delivery_methods' => $sellDeliveryMethods];
        }
        if ($this->buyDeliveryMethods !== null) {
            $buyDeliveryMethods = [];
            foreach ($this->buyDeliveryMethods as $method) {
                $buyDeliveryMethods[] = $method->toJson();
            }
            $result += ['buy_delivery_methods' => $buyDeliveryMethods];
        }

        if ($this->countryCodes !== null) {
            $countryCodes = [];
            foreach ($this->countryCodes as $code) {
                $countryCodes[] = $code;
            }
            $result += ['country_codes' => $countryCodes];
        }

        return $result;
    }
}
