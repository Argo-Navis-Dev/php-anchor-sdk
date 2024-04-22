<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep31\Sep31Service;
use ArgoNavis\Test\PhpAnchorSdk\callback\CrossBorderPaymentsIntegration;
use ArgoNavis\Test\PhpAnchorSdk\util\ServerRequestBuilder;
use Psr\Http\Message\ResponseInterface;
use Soneso\StellarSDK\SEP\CrossBorderPayments\SEP31InfoResponse;

use function assert;
use function count;
use function intval;
use function is_array;
use function json_decode;
use function key;
use function microtime;
use function strval;

class Sep31Test extends TestCase
{
    private string $infoEndpoint = 'https://test.com/sep31/info';
    private string $accountId = 'GCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6H2M';

    public function testGetInfo(): void
    {
        $crossBorderPaymentsIntegration = new CrossBorderPaymentsIntegration();
        $sep31Service = new Sep31Service($crossBorderPaymentsIntegration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $request = ServerRequestBuilder::getServerRequest($this->infoEndpoint, ['lang' => 'en']);
        $response = $sep31Service->handleRequest($request, $sep10Jwt);

        $info = $this->getSep31InfoResponse($response);

        self::assertEquals(1, count($info->receiveAssets));
        self::assertEquals(CrossBorderPaymentsIntegration::USDC, key($info->receiveAssets));
        $usdcInfo = $info->receiveAssets[CrossBorderPaymentsIntegration::USDC];
        self::assertEquals(CrossBorderPaymentsIntegration::QUOTES_SUPPORTED, $usdcInfo->quotesSupported);
        self::assertEquals(CrossBorderPaymentsIntegration::QUOTES_REQUIRED, $usdcInfo->quotesRequired);
        self::assertEquals(CrossBorderPaymentsIntegration::FEE_FIXED, $usdcInfo->feeFixed);
        self::assertEquals(CrossBorderPaymentsIntegration::FEE_PERCENT, $usdcInfo->feePercent);
        self::assertEquals(CrossBorderPaymentsIntegration::MIN_AMOUNT, $usdcInfo->minAmount);
        self::assertEquals(CrossBorderPaymentsIntegration::MAX_AMOUNT, $usdcInfo->maxAmount);
        self::assertNotNull($usdcInfo->sep12Info);

        $senderTypes = $usdcInfo->sep12Info->senderTypes;
        self::assertCount(3, $senderTypes);

        $sep31Sender = $senderTypes['sep31-sender'];
        self::assertNotNull($sep31Sender);
        self::assertEquals(
            'U.S. citizens limited to sending payments of less than $10,000 in value',
            $sep31Sender,
        );
        $sep31LargeSender = $senderTypes['sep31-large-sender'];
        self::assertNotNull($sep31LargeSender);
        self::assertEquals('U.S. citizens that do not have sending limits', $sep31LargeSender);
        $sep31ForeignSender = $senderTypes['sep31-foreign-sender'];
        self::assertNotNull($sep31ForeignSender);
        self::assertEquals(
            'non-U.S. citizens sending payments of less than $10,000 in value',
            $sep31ForeignSender,
        );

        $receiverTypes = $usdcInfo->sep12Info->receiverTypes;
        $sep31Receiver = $receiverTypes['sep31-receiver'];
        self::assertNotNull($sep31Receiver);
        self::assertEquals('U.S. citizens receiving USD', $sep31Receiver);
        $sep31ForeignReceiver = $receiverTypes['sep31-foreign-receiver'];
        self::assertNotNull($sep31ForeignReceiver);
        self::assertEquals('non-U.S. citizens receiving USD', $sep31ForeignReceiver);
    }

    private function createSep10Jwt(string $sub): Sep10Jwt
    {
        $iss = 'https://test.com/auth';
        $jti = 'test';
        $currentTime = intval(microtime(true));
        $iat = strval($currentTime);
        $exp = strval(($currentTime + 5 * 60));

        return new Sep10Jwt($iss, $sub, $iat, $exp, $jti);
    }

    private function getSep31InfoResponse(ResponseInterface $response): SEP31InfoResponse
    {
        self::assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP31InfoResponse::fromJson($decoded);
    }
}
