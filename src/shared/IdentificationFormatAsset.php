<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\shared;

use ArgoNavis\PhpAnchorSdk\exception\InvalidAsset;
use Soneso\StellarSDK\Crypto\KeyPair;
use Throwable;

use function count;
use function explode;
use function strlen;

/**
 * Asset as defined in [Asset Identification Format](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0038.md#asset-identification-format)
 */
class IdentificationFormatAsset
{
    public const NATIVE_ASSET_CODE = 'native';
    public const ASSET_SCHEMA_STELLAR = 'stellar';
    public const ASSET_SCHEMA_ISO4217 = 'iso4217';

    private string $schema;
    private string $code;
    private ?string $issuer = null;

    /**
     * @param string $schema schema of the asset as defined in the Asset Identification Format. Possible values are 'stellar' or 'iso4217'
     * @param string $code asset code
     * @param string|null $issuer asset issuer
     *
     * @throws InvalidAsset
     */
    public function __construct(string $schema, string $code, ?string $issuer = null)
    {
        self::validateSchema($schema);
        $this->schema = $schema;
        if ($schema === self::ASSET_SCHEMA_STELLAR) {
            self::validateStellarAssetCode($code);
            if ($issuer !== null) {
                self::validateStellarAssetIssuer($issuer);
            }
        } else {
            self::validateIso4217AssetCode($code);
        }

        $this->schema = $schema;
        $this->code = $code;
        $this->issuer = $issuer;
    }

    /**
     * @return bool true if the asset is a stellar asset.
     */
    public function isStellarAsset(): bool
    {
        return $this->schema === self::ASSET_SCHEMA_STELLAR;
    }

    /**
     * @return bool true if the asset is not a stellar asset.
     */
    public function isIso4217Asset(): bool
    {
        return $this->schema === self::ASSET_SCHEMA_ISO4217;
    }

    /**
     * @return bool true if the asset is the native stellar asset.
     */
    public function isStellarNativeAsset(): bool
    {
        return $this->schema === self::ASSET_SCHEMA_STELLAR && $this->code === self::NATIVE_ASSET_CODE;
    }

    /**
     * @return string the asset as string in Asset Identification Format.
     */
    public function getStringRepresentation(): string
    {
        $result = $this->schema . ':' . $this->code;
        if ($this->schema === self::ASSET_SCHEMA_STELLAR && $this->issuer !== null) {
            $result .= ':' . $this->issuer;
        }

        return $result;
    }

    /**
     * @throws InvalidAsset if the given asset string is not valid in terms of the asset identification format or
     * if any of the components is invalid, such as stellar asset code length > 12 characters.
     */
    public static function fromString(string $asset): IdentificationFormatAsset
    {
        $parts = explode(':', $asset, limit: 2);
        if (count($parts) === 2) {
            $schema = $parts[0];
            self::validateSchema($schema);

            $code = $parts[1];
            $issuer = null;
            if ($schema === self::ASSET_SCHEMA_STELLAR) {
                $codeParts = explode(':', $code);
                if (count($codeParts) > 1) {
                    $code = $codeParts[0];
                    $issuer = $codeParts[1];
                    self::validateStellarAssetIssuer($issuer);
                }
                self::validateStellarAssetCode($code);
            } else {
                self::validateIso4217AssetCode($code);
            }

            return new IdentificationFormatAsset($schema, $code, $issuer);
        } else {
            throw new InvalidAsset(
                message: 'the asset ' . $asset . ' has an invalid asset format',
                messageKey: 'asset_lang.error.invalid_asset_format',
                messageParams: ['asset' => $asset],
            );
        }
    }

    /**
     * @throws InvalidAsset if not valid
     */
    private static function validateSchema(string $schema): void
    {
        $valid = $schema === self::ASSET_SCHEMA_STELLAR || $schema === self::ASSET_SCHEMA_ISO4217;
        if (!$valid) {
            throw new InvalidAsset('schema can only be ' .
                self::ASSET_SCHEMA_STELLAR . ' or ' .
                self::ASSET_SCHEMA_ISO4217 . ' but ' . $schema . ' given.');
        }
    }

    /**
     * @throws InvalidAsset
     */
    private static function validateStellarAssetCode(string $code): void
    {
        if (strlen($code) > 12) {
            throw new InvalidAsset('stellar asset code has more than 12 characters');
        }
    }

    /**
     * @throws InvalidAsset
     */
    private static function validateIso4217AssetCode(string $code): void
    {
        if (strlen($code) !== 3) {
            throw new InvalidAsset('iso 4217 asset code must be 3 characters long');
        }
    }

    /**
     * @throws InvalidAsset
     */
    private static function validateStellarAssetIssuer(string $issuer): void
    {
        try {
            KeyPair::fromAccountId($issuer);
        } catch (Throwable) {
            throw new InvalidAsset('stellar asset issuer must be a stellar account id');
        }
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }
}
