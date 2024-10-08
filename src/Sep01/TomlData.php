<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep01;

use ArgoNavis\PhpAnchorSdk\exception\TomlDataNotLoaded;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use Laminas\Diactoros\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\SEP\Toml\Currencies;
use Soneso\StellarSDK\SEP\Toml\Documentation;
use Soneso\StellarSDK\SEP\Toml\GeneralInformation;
use Soneso\StellarSDK\SEP\Toml\Principals;
use Soneso\StellarSDK\SEP\Toml\StellarToml;
use Soneso\StellarSDK\SEP\Toml\Validators;
use Yosymfony\Toml\Exception\ParseException;

use function file_get_contents;

/**
 * This class can be used to construct or to parse stellar toml data as defined by
 * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0001.md">SEP-01</a>.
 * By using the constructor you can provide the data stored in arguments, for example from a database.
 * The stellar toml data can also be loaded from a given source such as from an url or from a file.
 * In these cases, the data will be parsed from the given source and the class members will
 * be filled from the parsed data. You can then read, and if needed, manipulate them.
 */
class TomlData
{
    public ?GeneralInformation $generalInformation = null;
    public ?Documentation $documentation = null;
    public ?Principals $principals = null;
    public ?Currencies $currencies = null;
    public ?Validators $validators = null;

    /**
     * Constructor.
     */
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
     * Loads a correctly formatted stellar toml file from the given url. Parses the data and initializes the TomlData object from it.
     *
     * @param string $url The url to load the data from.
     * @param ClientInterface $httpClient The http client to be used to load the data.
     * @param LoggerInterface|null $logger The logger to be used to logging.
     *
     * @return TomlData The loaded and parsed data as a TomlData object.
     *
     * @throws TomlDataNotLoaded if the data could not be loaded.
     */
    public static function fromUrl(string $url, ClientInterface $httpClient, ?LoggerInterface $logger = null): TomlData
    {
        $logger = $logger ?? new NullLogger();
        $logger->debug('Loading Stellar toml from url.', ['url' => $url, 'context' => 'sep01']);
        $request = new Request($url, 'GET');

        try {
            $response = $httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TomlDataNotLoaded(
                'Stellar toml could not be loaded: ' . $e->getMessage(),
                code: $e->getCode(),
                previous: $e,
            );
        }

        if ($response->getStatusCode() !== 200) {
            throw new TomlDataNotLoaded(
                'Stellar toml not found. Response status code ' . $response->getStatusCode(),
                code: $response->getStatusCode(),
            );
        }

        $tomlContent = self::fromString((string) $response->getBody());
        $logger->debug('The toml file content.', ['context' => 'sep01', 'content' => $tomlContent]);

        return $tomlContent;
    }

    /**
     * Loads a correctly formatted stellar toml file from the given file path. Parses the data and initializes the TomlData object from it.
     *
     * @param string $pathToFile Path to the file to load and parse the data from.
     * @param LoggerInterface|null $logger The logger to be used to logging.
     *
     * @return TomlData The loaded and parsed data as a TomlData object.
     *
     * @throws TomlDataNotLoaded if the data could not be loaded.
     * @throws ParseException if the loaded data is not correctly formatted and could not be parsed.
     */
    public static function fromFile(string $pathToFile, ?LoggerInterface $logger = null): TomlData
    {
        $logger = $logger ?? new NullLogger();
        $logger->debug('Loading Stellar toml from file.', ['path_to_file' => $pathToFile, 'context' => 'sep01']);
        $fileContent = file_get_contents($pathToFile, false);
        if ($fileContent === false) {
            throw new TomlDataNotLoaded('File content could not be loaded for: ' . $pathToFile, code: 404);
        }
        $tomlContent = self::fromString($fileContent);
        $logger->debug('The toml file content.', ['context' => 'sep01', 'content' => $tomlContent]);

        return $tomlContent;
    }

    /**
     * Loads a correctly formatted stellar toml file from the given string. Parses the data and initializes the TomlData object from it.
     *
     * @param string $toml The string containing the stellar toml data.
     *
     * @return TomlData The loaded and parsed data as a TomlData object.
     *
     * @throws ParseException if the data is not correctly formatted and could not be parsed.
     */
    public static function fromString(string $toml): TomlData
    {
        $stellarToml = new StellarToml($toml);
        $generalInfo = $stellarToml->getGeneralInformation();
        $doc = $stellarToml->getDocumentation();
        $principals = $stellarToml->getPrincipals();
        $currencies = $stellarToml->getCurrencies();
        $validators = $stellarToml->getValidators();

        return new TomlData($generalInfo, $doc, $principals, $currencies, $validators);
    }

    /**
     * Loads the stellar toml data from a domain. Parses the data and initializes the TomlData object from it.
     *
     * @param string $domain Domain to fetch the stellar.toml file from 'https://' . $domain . '/.well-known/stellar.toml';
     * @param ClientInterface $httpClient The http client to be used to fetch the data.
     *
     * @return TomlData The loaded and parsed data as a TomlData object.
     *
     * @throws TomlDataNotLoaded if the data could not be loaded.
     * @throws ParseException if the loaded data is not correctly formatted and could not be parsed.
     */
    public static function fromDomain(string $domain, ClientInterface $httpClient): TomlData
    {
        $url = 'https://' . $domain . '/.well-known/stellar.toml';

        return self::fromUrl($url, $httpClient, null);
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
