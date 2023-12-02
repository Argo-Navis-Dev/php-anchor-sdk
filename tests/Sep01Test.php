<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep01\TomlData;
use ArgoNavis\PhpAnchorSdk\Sep01\TomlProvider;
use GuzzleHttp\Client;
use Soneso\StellarSDK\SEP\Toml\Currencies;
use Soneso\StellarSDK\SEP\Toml\Currency;
use Soneso\StellarSDK\SEP\Toml\Documentation;
use Soneso\StellarSDK\SEP\Toml\GeneralInformation;
use Soneso\StellarSDK\SEP\Toml\PointOfContact;
use Soneso\StellarSDK\SEP\Toml\Principals;
use Soneso\StellarSDK\SEP\Toml\Validator;
use Soneso\StellarSDK\SEP\Toml\Validators;

class Sep01Test extends TestCase
{
    public function testWithData(): void
    {
        $tomlData = self::composeTomlData();
        $provider = new TomlProvider();
        $tomlResponse = $provider->handleFromData($tomlData);
        $this->assertEquals(200, $tomlResponse->getStatusCode());

        $tomlString = $tomlResponse->getBody()->__toString();
        $parsed = TomlData::fromString($tomlString);
        self::assertEquals($parsed, $tomlData);
    }

    public function testWithFile(): void
    {
        $pathToFile = __DIR__ . '/toml/stellar.toml';
        $tomlData = self::composeTomlData();
        $provider = new TomlProvider();
        $tomlResponse = $provider->handleFromFile($pathToFile);
        $this->assertEquals(200, $tomlResponse->getStatusCode());

        $tomlString = $tomlResponse->getBody()->__toString();
        $parsed = TomlData::fromString($tomlString);

        self::assertEquals($parsed, $tomlData);
        $parsed = TomlData::fromFile($pathToFile);
        self::assertEquals($parsed, $tomlData);
    }

    public function testFromDomain(): void
    {
        $tomlData = TomlData::fromDomain('ultrastellar.com', new Client());
        self::assertEquals('2.2.0', $tomlData->generalInformation->version);
        $provider = new TomlProvider();
        $tomlResponse = $provider->handleFromUrl('https://ultrastellar.com/.well-known/stellar.toml', new Client());
        $tomlString = $tomlResponse->getBody()->__toString();
        $parsed = TomlData::fromString($tomlString);
        self::assertEquals($parsed, $tomlData);
    }

    private function composeTomlData(): TomlData
    {
        $generalInfo = new GeneralInformation();
        $generalInfo->version = '2.0.0';
        $generalInfo->networkPassphrase = 'Public Global Stellar Network ; September 2015';
        $generalInfo->federationServer = 'https://federation.argo-navis.dev';
        $generalInfo->authServer = 'https://auth.argo-navis.dev';
        $generalInfo->transferServer = 'https://transfer.argo-navis.dev';
        $generalInfo->transferServerSep24 = 'https://transfer_sep24.argo-navis.dev';
        $generalInfo->kYCServer = 'https://kyc.argo-navis.dev';
        $generalInfo->webAuthEndpoint = 'https://web_auth.argo-navis.dev';
        $generalInfo->signingKey = 'GBTVIUH5FRB5DQ6MWCWBAXTHGBGVNKRXGX2AJCVAKXBBW2ZKGOT6ZL4E';
        $generalInfo->horizonUrl = 'https://horizon.argo-navis.dev';
        $generalInfo->accounts = ['GCLQF44JZZQKQX5XRASAEVG2PAJZZWKHMCDZGLLO4BMOSPN6IYTNCJQX',
            'GAIE56EEB4TOKPABWGWGMDCRB5JS2TWFZRDOXBIZ4IY4TU6V6X6M7SU4',
            'GCUMMMMOEVE7WYLDC66GUW5VLAU46LPTQFWBELJWXNBUQVOYWZD6SDSD',
            'GA5QZADVLLS56JZCX5LIFW3MZ5ONLSA4OH6QZZMKRYQ3XN5OQGI7MIKB',
        ];
        $generalInfo->uriRequestSigningKey = 'GCLQF44JZZQKQX5XRASAEVG2PAJZZWKHMCDZGLLO4BMOSPN6IYTNCJQX';
        $generalInfo->directPaymentServer = 'https://dir_pay.argo-navis.dev';
        $generalInfo->anchorQuoteServer = 'https://anchor_quote.argo-navis.dev';

        $doc = new Documentation();
        $doc->orgName = 'Argo Navis Dev';
        $doc->orgDBA = 'DBA';
        $doc->orgUrl = 'https://argo-navis.dev';
        $doc->orgLogo = 'https://argo-navis.dev/logo.png';
        $doc->orgDescription = 'Argo Navis Dev provides development services related to Stellar';
        $doc->orgPhysicalAddress = 'address';
        $doc->orgPhysicalAddressAttestation = 'https://argo-navis.dev/paddrattest.pdf';
        $doc->orgPhoneNumber = '1234567890';
        $doc->orgPhoneNumberAttestation = 'https://argo-navis.dev/phnrattest.pdf';
        $doc->orgKeybase = 'keybase';
        $doc->orgTwitter = 'twitter';
        $doc->orgGithub = 'Argo-Navis-Dev';
        $doc->orgOfficialEmail = 'info@argo-navis.dev';
        $doc->orgSupportEmail = 'support@argo-navis.dev';
        $doc->orgLicensingAuthority = 'l-auth';
        $doc->orgLicenseType = 'l-type';
        $doc->orgLicenseNumber = '1234';

        $principals = new Principals();
        $firstPoc = new PointOfContact();
        $firstPoc->name = 'Bence';
        $firstPoc->email = 'bence@argo-navis.dev';
        $firstPoc->keybase = 'bence-keybase';
        $firstPoc->telegram = 'bence-telegram';
        $firstPoc->twitter = 'bence-twitter';
        $firstPoc->github = 'bence-github';
        $firstPoc->idPhotoHash = 'a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e';
        $firstPoc->verificationPhotoHash = 'b221b6d40bf420404a011733cfb7b190d62c65bf0abcd32b57b277d9ad9f199a';
        $principals->add($firstPoc);

        $secondPoc = new PointOfContact();
        $secondPoc->name = 'Christian';
        $secondPoc->email = 'christian@argo-navis.dev';
        $secondPoc->keybase = 'christian-keybase';
        $secondPoc->telegram = 'christian-telegram';
        $secondPoc->twitter = 'christian-twitter';
        $secondPoc->github = 'christian-github';
        $secondPoc->idPhotoHash = 'f420404a0117a591a6d40b33cfb7b190d277d9ad9f146e62c65bf0bcda32b57b';
        $secondPoc->verificationPhotoHash = '04a011733cfb7b19b221b6d40bf42040db57b277d9ad9f199a62c65bf0abcd32';
        $principals->add($secondPoc);

        $currencies = new Currencies();
        $tXLM = new Currency();
        $tXLM->code = 'tXLM';
        $tXLM->codeTemplate = 'tXLM???';
        $tXLM->issuer = 'GCLQF44JZZQKQX5XRASAEVG2PAJZZWKHMCDZGLLO4BMOSPN6IYTNCJQX';
        $tXLM->status = 'test';
        $tXLM->displayDecimals = 4;
        $tXLM->name = 'Test XLM';
        $tXLM->desc = 'A test token for the stellar toml test.';
        $tXLM->conditions = 'tXLM conditions';
        $tXLM->image = 'https://argo-navis.dev/tXLM.png';
        $tXLM->fixedNumber = 12;
        $tXLM->maxNumber = 13;
        $tXLM->isUnlimited = false;
        $tXLM->isAssetAnchored = true;
        $tXLM->anchorAssetType = 'crypto';
        $tXLM->anchorAsset = 'XLM';

        //$tXLM->attestationOfReserve = 'https://argo-navis.dev/tXLM-att-or.pdf';
        $tXLM->redemptionInstructions = 'tXLM redemption instructions';
        $tXLM->collateralAddresses = ['GAIE56EEB4TOKPABWGWGMDCRB5JS2TWFZRDOXBIZ4IY4TU6V6X6M7SU4',
            'GCUMMMMOEVE7WYLDC66GUW5VLAU46LPTQFWBELJWXNBUQVOYWZD6SDSD',
        ];
        $tXLM->collateralAddressMessages = ['tXLM message one', 'tXLM message two'];
        $sig1 = 'w6r3RxucUNClymjp06Vx/XdxlBaQMBoshe9XPKcjPGemfFPhbrGe/SIZGvmttPd8EOIDCmB6SWJyeofjN8QNBA==';
        $sig2 = 'j/whXcN+Nf+n1iuQ7bNeNLuV8zVjusawfJUv8fSrJVlKKBAvbdBiRmXRzKPRhjOkkFV+4Nlpgr22TmEbKs4jDw==';

        $tXLM->collateralAddressSignatures = [$sig1, $sig2];
        $tXLM->regulated = true;
        $tXLM->approvalServer = 'https://txml_appr.argo-navis.dev';
        $tXLM->approvalCriteria = 'only for humans';

        $currencies->add($tXLM);

        $bXLM = new Currency();
        $bXLM->code = 'bXLM';
        $bXLM->codeTemplate = 'bXLM???';
        $bXLM->issuer = 'GA5QZADVLLS56JZCX5LIFW3MZ5ONLSA4OH6QZZMKRYQ3XN5OQGI7MIKB';
        $bXLM->status = 'public';
        $bXLM->displayDecimals = 6;
        $bXLM->name = 'Test b for XLM';
        $bXLM->desc = 'Another test token for the stellar toml test.';
        $bXLM->conditions = 'bXLM conditions';
        $bXLM->image = 'https://argo-navis.dev/bXLM.png';
        $bXLM->fixedNumber = 65;
        $bXLM->maxNumber = 66;
        $bXLM->isUnlimited = true;
        $bXLM->isAssetAnchored = false;
        $bXLM->anchorAssetType = 'other';
        $bXLM->anchorAsset = 'MOON';

        //$tXLM->attestationOfReserve = 'https://argo-navis.dev/bXLM-att-or.pdf';
        $bXLM->redemptionInstructions = 'bXLM redemption instructions';
        $bXLM->collateralAddresses = ['GCUMMMMOEVE7WYLDC66GUW5VLAU46LPTQFWBELJWXNBUQVOYWZD6SDSD',
            'GAIE56EEB4TOKPABWGWGMDCRB5JS2TWFZRDOXBIZ4IY4TU6V6X6M7SU4',
        ];
        $bXLM->collateralAddressMessages = ['bXLM message one', 'bXLM message two'];
        $sig1 = 'lBaQMBoshe9XPKcjPGemOIDCmB6w6r3RxucUNClymjp06Vx/XdxSWJyeofjN8QNBAfFPhbrGe/SIZGvmttPd8E==';
        $sig2 = 'VlKKBAvbdBiRmXRj/whXcN+Nf+n1iuQ7bNeNLuVPRhjOkkFV+4Nlpgr22TmEbKs4jDw8zVjusawfJUv8fSrJzK==';
        $bXLM->collateralAddressSignatures = [$sig1, $sig2];
        $bXLM->regulated = true;
        $bXLM->approvalServer = 'https://bxml_appr.argo-navis.dev';
        $bXLM->approvalCriteria = 'only for pets';

        $currencies->add($bXLM);

        $validators = new Validators();
        $validator1 = new Validator();
        $validator1->alias = 'vali 1';
        $validator1->displayName = 'Validator 1';
        $validator1->publicKey = 'GCWPH7VG2J37XJX34R344KVR7RQ6M52WPVKOWNOJ33N7TWT4P6LS5YWY';
        $validator1->host = 'validator1.argo-navis.dev:5003';
        $validator1->history = 'https://history.argo-navis.dev/val1';
        $validators->add($validator1);

        $validator2 = new Validator();
        $validator2->alias = 'vali 2';
        $validator2->displayName = 'Validator 2';
        $validator2->publicKey = 'GCEB2YK5ISAWJFSMFXSHZT44OOCZVV4PQOSYM4OBC3BCLNADLKYLWYX2';
        $validator2->host = 'validator2.argo-navis.dev:5003';
        $validator2->history = 'https://history.argo-navis.dev/val2';
        $validators->add($validator2);

        return new TomlData(
            generalInformation: $generalInfo,
            documentation: $doc,
            principals: $principals,
            currencies: $currencies,
            validators: $validators,
        );
    }
}
