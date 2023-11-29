<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\SEP\Toml;

use Laminas\Diactoros\Request;
use Psr\Http\Client\ClientInterface;
use Soneso\StellarSDK\SEP\Toml\Currencies;
use Soneso\StellarSDK\SEP\Toml\Documentation;
use Soneso\StellarSDK\SEP\Toml\GeneralInformation;
use Soneso\StellarSDK\SEP\Toml\Principals;
use Soneso\StellarSDK\SEP\Toml\StellarToml;
use Soneso\StellarSDK\SEP\Toml\Validators;

class TomlData
{
    private ?GeneralInformation $generalInformation = null;
    private ?Documentation $documentation = null;
    private ?Principals $principals = null;
    private ?Currencies $currencies = null;
    private ?Validators $validators = null;

    public function __construct(
        ?GeneralInformation $generalInformation = null,
        ?Documentation $documentation = null,
        ?Principals $principals = null,
        ?Currencies $currencies = null,
        ?Validators $validators = null,
    ) {
        $this->generalInformation = $generalInformation;
        $this->documentation = $documentation;
        $this->principals = $principals;
        $this->currencies = $currencies;
        $this->validators = $validators;
    }

    /**
     * @throws TomlDataLoadingException
     */
    public static function fromUrl(string $url, ClientInterface $httpClient): TomlData
    {
        $request = new Request($url, 'GET');
        $response = $httpClient->send($request);
        if ($response->getStatusCode() !== 200) {
            $msg = 'Stellar toml not found. Response status code ' . $response->getStatusCode();

            throw new TomlDataLoadingException($msg, code: $response->getStatusCode());
        }

        return self::fromString((string) $response->getBody());
    }

    public static function fromString(string $toml): TomlData
    {
        $toml = new StellarToml($toml);
        $generalInfo = $toml->getGeneralInformation();
        $doc = $toml->getDocumentation();
        $principals = $toml->getPrincipals();
        $currencies = $toml->getCurrencies();
        $validators = $toml->getValidators();

        return new TomlData($generalInfo, $doc, $principals, $currencies, $validators);
    }

    public static function fromDomain(string $domain, ClientInterface $httpClient): TomlData
    {
        $url = 'https://' . $domain . '/.well-known/stellar.toml';

        return self::fromUrl($url, $httpClient);
    }

    public function getGeneralInformation(): ?GeneralInformation
    {
        return $this->generalInformation;
    }

    public function setGeneralInformation(?GeneralInformation $generalInformation): void
    {
        $this->generalInformation = $generalInformation;
    }

    public function getDocumentation(): ?Documentation
    {
        return $this->documentation;
    }

    public function setDocumentation(?Documentation $documentation): void
    {
        $this->documentation = $documentation;
    }

    public function getPrincipals(): ?Principals
    {
        return $this->principals;
    }

    public function setPrincipals(?Principals $principals): void
    {
        $this->principals = $principals;
    }

    public function getCurrencies(): ?Currencies
    {
        return $this->currencies;
    }

    public function setCurrencies(?Currencies $currencies): void
    {
        $this->currencies = $currencies;
    }

    public function getValidators(): ?Validators
    {
        return $this->validators;
    }

    public function setValidators(?Validators $validators): void
    {
        $this->validators = $validators;
    }
}
