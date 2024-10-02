<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep01;

use ArgoNavis\PhpAnchorSdk\exception\TomlDataNotLoaded;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\SEP\Toml\Currencies;
use Soneso\StellarSDK\SEP\Toml\Documentation;
use Soneso\StellarSDK\SEP\Toml\GeneralInformation;
use Soneso\StellarSDK\SEP\Toml\Principals;
use Soneso\StellarSDK\SEP\Toml\Validators;
use Yosymfony\Toml\TomlBuilder;

use function count;
use function file_get_contents;
use function json_encode;

/**
 * This class can be used to construct a http response containing the stellar toml data that is formatted
 * as defined by <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0001.md">SEP-01</a>.
 * You can either provide the input data by building a TomlData object, the path to a file or an url.
 * If you provide a path to a file or an url, the data must already be correctly formatted.
 */
class TomlProvider
{
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private LoggerInterface | NullLogger $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Constructs the stellar.toml file from the given data.
     *
     * @param TomlData $data data to construct the stellar.toml file from.
     *
     * @return ResponseInterface response with content type 'text/plain' and status 200 containing the formatted data in the body.
     */
    public function handleFromData(TomlData $data): ResponseInterface
    {
        $this->logger->info(
            'Generating Stellar toml out of the passed data object.',
            ['context' => 'sep01'],
        );
        $result = $this->buildFromData($data);
        $this->logger->debug(
            'Stellar toml data generated successfully out of the passed data object.',
            ['context' => 'sep01'],
        );

        return new TextResponse($result, status: 200);
    }

    /**
     * Returns the content of the given stellar toml file as a response.
     *
     * @param String $pathToFile the path to the file containing the already formatted stellar toml data.
     *
     * @return ResponseInterface response with content type 'text/plain' and status 200 containing the formatted data in the body.
     *
     * @throws TomlDataNotLoaded if the given file could not be read.
     */
    public function handleFromFile(string $pathToFile): ResponseInterface
    {
        $this->logger->info(
            'Loading stellar toml data from file.',
            ['context' => 'sep01', 'file path' => $pathToFile],
        );
        $fileContent = file_get_contents($pathToFile, false);
        if ($fileContent === false) {
            $msg = 'File content could not be loaded for: ' . $pathToFile;
            $this->logger->error(
                'Failed to return the stellar toml file content, error: ' . $msg,
                ['context' => 'sep01'],
            );

            throw new TomlDataNotLoaded($msg, code: 404);
        }
        $this->logger->debug(
            'Loading stellar toml data from file has been finished successfully.',
            ['context' => 'sep01'],
        );

        return new TextResponse($fileContent, status: 200);
    }

    /**
     * Loads the stellar toml data from a given url and returns a new response containing the loaded data.
     *
     * @param String $url the url to load the data from.
     * @param ClientInterface $httpClient the http client to be used for loading the data.
     *
     * @return ResponseInterface response with content type 'text/plain' and status 200 containing the formatted data in the body.
     *
     * @throws TomlDataNotLoaded if the data could not be loaded from the given url.
     */
    public function handleFromUrl(string $url, ClientInterface $httpClient): ResponseInterface
    {
        $this->logger->info(
            'Loading stellar toml data from URL.',
            ['url' => $url, 'context' => 'sep01'],
        );
        $request = new Request($url, 'GET');
        try {
            $response = $httpClient->sendRequest($request);
            $this->logger->debug(
                'Data loaded from URL successfully.',
                ['url' => $url, 'context' => 'sep01'],
            );
        } catch (ClientExceptionInterface $e) {
            $msg = 'Stellar toml could not be loaded: ' . $e->getMessage();
            $this->logger->error(
                $msg,
                ['url' => $url, 'error' => $e->getMessage(), 'context' => 'sep01'],
            );

            throw new TomlDataNotLoaded($msg, code: $e->getCode(), previous: $e);
        }

        if ($response->getStatusCode() !== 200) {
            $msg = 'Stellar toml could not be loaded from url: ' . $url;
            $msg .= ' Response status code ' . $response->getStatusCode();
            $this->logger->error(
                'Stellar toml data could not be loaded from url.',
                ['url' => $url, 'status_code' => $response->getStatusCode(), 'context' => 'sep01'],
            );

            throw new TomlDataNotLoaded($msg, code: $response->getStatusCode());
        }
        $this->logger->debug(
            'Loading stellar toml data from url has been finished successfully.',
            ['url' => $url, 'context' => 'sep01'],
        );

        return new TextResponse($response->getBody(), status: 200);
    }

    private function buildFromData(TomlData $data): string
    {
        $this->logger->debug('Building stellar toml from provided data.', ['context' => 'sep01']);

        $tb = new TomlBuilder();
        $this->addGeneralInformation($tb, $data->getGeneralInformation());
        $this->addDocumentation($tb, $data->getDocumentation());
        $this->addPrincipals($tb, $data->getPrincipals());
        $this->addCurrencies($tb, $data->getCurrencies());
        $this->addValidators($tb, $data->getValidators());

        return $tb->getTomlString();
    }

    private function addGeneralInformation(TomlBuilder $builder, ?GeneralInformation $gI): void
    {
        if ($gI === null) {
            $this->logger->warning('Stellar toml data general information part is null.', ['context' => 'sep01']);

            return;
        }
        $this->logger->debug(
            'Building stellar toml general information part.',
            ['general_information' => json_encode($gI), 'context' => 'sep01'],
        );
        if ($gI->version !== null) {
            $builder->addValue('VERSION', $gI->version);
        }
        if ($gI->networkPassphrase !== null) {
            $builder->addValue('NETWORK_PASSPHRASE', $gI->networkPassphrase);
        }
        if ($gI->federationServer !== null) {
            $builder->addValue('FEDERATION_SERVER', $gI->federationServer);
        }
        if ($gI->authServer !== null) {
            $builder->addValue('AUTH_SERVER', $gI->authServer);
        }
        if ($gI->transferServer !== null) {
            $builder->addValue('TRANSFER_SERVER', $gI->transferServer);
        }
        if ($gI->transferServerSep24 !== null) {
            $builder->addValue('TRANSFER_SERVER_SEP0024', $gI->transferServerSep24);
        }
        if ($gI->kYCServer !== null) {
            $builder->addValue('KYC_SERVER', $gI->kYCServer);
        }
        if ($gI->webAuthEndpoint !== null) {
            $builder->addValue('WEB_AUTH_ENDPOINT', $gI->webAuthEndpoint);
        }
        if ($gI->signingKey !== null) {
            $builder->addValue('SIGNING_KEY', $gI->signingKey);
        }
        if ($gI->horizonUrl !== null) {
            $builder->addValue('HORIZON_URL', $gI->horizonUrl);
        }
        if (count($gI->accounts) > 0) {
            $builder->addValue('ACCOUNTS', $gI->accounts);
        }
        if ($gI->uriRequestSigningKey !== null) {
            $builder->addValue('URI_REQUEST_SIGNING_KEY', $gI->uriRequestSigningKey);
        }
        if ($gI->directPaymentServer !== null) {
            $builder->addValue('DIRECT_PAYMENT_SERVER', $gI->directPaymentServer);
        }
        if ($gI->anchorQuoteServer !== null) {
            $builder->addValue('ANCHOR_QUOTE_SERVER', $gI->anchorQuoteServer);
        }
    }

    private function addDocumentation(TomlBuilder $builder, ?Documentation $doc): void
    {
        if ($doc === null) {
            $this->logger->warning('Stellar toml data documentation part is null.', ['context' => 'sep01']);

            return;
        } else {
            $builder->addTable('DOCUMENTATION');
        }
        $this->logger->debug(
            'Building stellar toml documentation part.',
            ['documentation' => json_encode($doc), 'context' => 'sep01'],
        );
        if ($doc->orgName !== null) {
            $builder->addValue('ORG_NAME', $doc->orgName);
        }
        if ($doc->orgDBA !== null) {
            $builder->addValue('ORG_DBA', $doc->orgDBA);
        }
        if ($doc->orgUrl !== null) {
            $builder->addValue('ORG_URL', $doc->orgUrl);
        }
        if ($doc->orgLogo !== null) {
            $builder->addValue('ORG_LOGO', $doc->orgLogo);
        }
        if ($doc->orgDescription !== null) {
            $builder->addValue('ORG_DESCRIPTION', $doc->orgDescription);
        }
        if ($doc->orgPhysicalAddress !== null) {
            $builder->addValue('ORG_PHYSICAL_ADDRESS', $doc->orgPhysicalAddress);
        }
        if ($doc->orgPhysicalAddressAttestation !== null) {
            $builder->addValue('ORG_PHYSICAL_ADDRESS_ATTESTATION', $doc->orgPhysicalAddressAttestation);
        }
        if ($doc->orgPhoneNumber !== null) {
            $builder->addValue('ORG_PHONE_NUMBER', $doc->orgPhoneNumber);
        }
        if ($doc->orgPhoneNumberAttestation !== null) {
            $builder->addValue('ORG_PHONE_NUMBER_ATTESTATION', $doc->orgPhoneNumberAttestation);
        }
        if ($doc->orgKeybase !== null) {
            $builder->addValue('ORG_KEYBASE', $doc->orgKeybase);
        }
        if ($doc->orgTwitter !== null) {
            $builder->addValue('ORG_TWITTER', $doc->orgTwitter);
        }
        if ($doc->orgGithub !== null) {
            $builder->addValue('ORG_GITHUB', $doc->orgGithub);
        }
        if ($doc->orgOfficialEmail !== null) {
            $builder->addValue('ORG_OFFICIAL_EMAIL', $doc->orgOfficialEmail);
        }
        if ($doc->orgSupportEmail !== null) {
            $builder->addValue('ORG_SUPPORT_EMAIL', $doc->orgSupportEmail);
        }
        if ($doc->orgLicensingAuthority !== null) {
            $builder->addValue('ORG_LICENSING_AUTHORITY', $doc->orgLicensingAuthority);
        }
        if ($doc->orgLicenseType !== null) {
            $builder->addValue('ORG_LICENSE_TYPE', $doc->orgLicenseType);
        }
        if ($doc->orgLicenseNumber !== null) {
            $builder->addValue('ORG_LICENSE_NUMBER', $doc->orgLicenseNumber);
        }
    }

    private function addPrincipals(TomlBuilder $builder, ?Principals $principals): void
    {
        if ($principals === null) {
            $this->logger->warning('Stellar toml data principals part is null.', ['context' => 'sep01']);

            return;
        }

        $pArr = $principals->toArray();
        if (count($pArr) === 0) {
            $this->logger->warning('Stellar toml data principals part array empty.', ['context' => 'sep01']);

            return;
        }

        $this->logger->debug(
            'Building stellar toml principals part.',
            ['principals' => json_encode($principals), 'context' => 'sep01'],
        );
        foreach ($pArr as $poc) {
            $builder->addArrayOfTable('PRINCIPALS');
            if ($poc->name !== null) {
                $builder->addValue('name', $poc->name);
            }
            if ($poc->email !== null) {
                $builder->addValue('email', $poc->email);
            }
            if ($poc->keybase !== null) {
                $builder->addValue('keybase', $poc->keybase);
            }
            if ($poc->twitter !== null) {
                $builder->addValue('twitter', $poc->twitter);
            }
            if ($poc->telegram !== null) {
                $builder->addValue('telegram', $poc->telegram);
            }
            if ($poc->github !== null) {
                $builder->addValue('github', $poc->github);
            }
            if ($poc->idPhotoHash !== null) {
                $builder->addValue('id_photo_hash', $poc->idPhotoHash);
            }
            if ($poc->verificationPhotoHash !== null) {
                $builder->addValue('verification_photo_hash', $poc->verificationPhotoHash);
            }
        }
    }

    private function addCurrencies(TomlBuilder $builder, ?Currencies $currencies): void
    {
        if ($currencies === null) {
            $this->logger->debug('Stellar toml data currencies part is null.', ['context' => 'sep01']);

            return;
        }

        $cArr = $currencies->toArray();
        if (count($cArr) === 0) {
            $this->logger->debug('Stellar toml data currencies part is empty.', ['context' => 'sep01']);

            return;
        }

        $this->logger->debug(
            'Building stellar toml currencies part.',
            ['currencies' => json_encode($currencies), 'context' => 'sep01'],
        );
        foreach ($cArr as $cur) {
            $builder->addArrayOfTable('CURRENCIES');
            if ($cur->toml !== null) {
                $builder->addValue('toml', $cur->toml);
            }
            if ($cur->code !== null) {
                $builder->addValue('code', $cur->code);
            }
            if ($cur->codeTemplate !== null) {
                $builder->addValue('code_template', $cur->codeTemplate);
            }
            if ($cur->issuer !== null) {
                $builder->addValue('issuer', $cur->issuer);
            }
            if ($cur->status !== null) {
                $builder->addValue('status', $cur->status);
            }
            if ($cur->displayDecimals !== null) {
                $builder->addValue('display_decimals', $cur->displayDecimals);
            }
            if ($cur->name !== null) {
                $builder->addValue('name', $cur->name);
            }
            if ($cur->desc !== null) {
                $builder->addValue('desc', $cur->desc);
            }
            if ($cur->conditions !== null) {
                $builder->addValue('conditions', $cur->conditions);
            }
            if ($cur->image !== null) {
                $builder->addValue('image', $cur->image);
            }
            if ($cur->fixedNumber !== null) {
                $builder->addValue('fixed_number', $cur->fixedNumber);
            }
            if ($cur->maxNumber !== null) {
                $builder->addValue('max_number', $cur->maxNumber);
            }
            if ($cur->isUnlimited !== null) {
                $builder->addValue('is_unlimited', $cur->isUnlimited);
            }
            if ($cur->isAssetAnchored !== null) {
                $builder->addValue('is_asset_anchored', $cur->isAssetAnchored);
            }
            if ($cur->anchorAssetType !== null) {
                $builder->addValue('anchor_asset_type', $cur->anchorAssetType);
            }
            if ($cur->anchorAsset !== null) {
                $builder->addValue('anchor_asset', $cur->anchorAsset);
            }

            //if ($cur->attestationOfReserve !== null) {
            //    $builder->addValue('attestation_of_reserve', $cur->attestationOfReserve);
            //}

            if ($cur->redemptionInstructions !== null) {
                $builder->addValue('redemption_instructions', $cur->redemptionInstructions);
            }
            if ($cur->collateralAddresses !== null) {
                $builder->addValue('collateral_addresses', $cur->collateralAddresses);
            }
            if ($cur->collateralAddressMessages !== null) {
                $builder->addValue('collateral_address_messages', $cur->collateralAddressMessages);
            }
            if ($cur->collateralAddressSignatures !== null) {
                $builder->addValue('collateral_address_signatures', $cur->collateralAddressSignatures);
            }
            if ($cur->regulated !== null) {
                $builder->addValue('regulated', $cur->regulated);
            }
            if ($cur->approvalServer !== null) {
                $builder->addValue('approval_server', $cur->approvalServer);
            }
            if ($cur->approvalCriteria !== null) {
                $builder->addValue('approval_criteria', $cur->approvalCriteria);
            }
        }
    }

    private function addValidators(TomlBuilder $builder, ?Validators $validators): void
    {
        if ($validators === null) {
            $this->logger->debug('Stellar toml data validators part is null.', ['context' => 'sep01']);

            return;
        }

        $vArr = $validators->toArray();
        if (count($vArr) === 0) {
            $this->logger->debug('Stellar toml data validators part is empty.', ['context' => 'sep01']);

            return;
        }

        $this->logger->debug(
            'Building Stellar toml validators part.',
            ['validators' => json_encode($validators), 'context' => 'sep01'],
        );
        foreach ($vArr as $validator) {
            $builder->addArrayOfTable('VALIDATORS');
            if ($validator->alias !== null) {
                $builder->addValue('ALIAS', $validator->alias);
            }
            if ($validator->displayName !== null) {
                $builder->addValue('DISPLAY_NAME', $validator->displayName);
            }
            if ($validator->publicKey !== null) {
                $builder->addValue('PUBLIC_KEY', $validator->publicKey);
            }
            if ($validator->host !== null) {
                $builder->addValue('HOST', $validator->host);
            }
            if ($validator->history !== null) {
                $builder->addValue('HISTORY', $validator->history);
            }
        }
    }
}
