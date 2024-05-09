<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\Sep38\Sep38Service;
use ArgoNavis\Test\PhpAnchorSdk\callback\QuotesIntegration;
use ArgoNavis\Test\PhpAnchorSdk\util\ServerRequestBuilder;
use DateTime;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soneso\StellarSDK\SEP\Quote\SEP38InfoResponse;
use Soneso\StellarSDK\SEP\Quote\SEP38PriceResponse;
use Soneso\StellarSDK\SEP\Quote\SEP38PricesResponse;
use Soneso\StellarSDK\SEP\Quote\SEP38QuoteResponse;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertTrue;
use function assert;
use function error_reporting;
use function intval;
use function is_array;
use function is_string;
use function json_decode;
use function microtime;
use function strval;

use const DATE_ATOM;
use const E_ALL;

class Sep38Test extends TestCase
{
    private string $infoEndpoint = 'https://test.com/sep38/info';
    private string $pricesEndpoint = 'https://test.com/sep38/prices';
    private string $priceEndpoint = 'https://test.com/sep38/price';
    private string $quoteEndpoint = 'https://test.com/sep38/quote';

    private string $accountId = 'GCUIGD4V6U7ATOUSC6IYSJCK7ZBKGN73YXN5VBMAKUY44FAASJBO6H2M';

    public function setUp(): void
    {
        // Turn on error reporting
        error_reporting(E_ALL);
    }

    public function testGetInfo(): void
    {
        $integration = new QuotesIntegration();
        $sep38Service = new Sep38Service(sep38Integration: $integration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $request = ServerRequestBuilder::getServerRequest($this->infoEndpoint, []);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $info = $this->getSep38InfoResponse($response);
        self::assertCount(3, $info->assets);

        $assetInfo = $info->assets[0];
        self::assertEquals(QuotesIntegration::$stellarUSDCStr, $assetInfo->asset);

        $assetInfo = $info->assets[1];
        self::assertEquals(QuotesIntegration::$stellarBRLStr, $assetInfo->asset);

        $assetInfo = $info->assets[2];
        self::assertEquals(QuotesIntegration::$iso4217BRLStr, $assetInfo->asset);
        self::assertNotNull($assetInfo->countryCodes);
        self::assertCount(1, $assetInfo->countryCodes);
        self::assertContains('BRA', $assetInfo->countryCodes);

        self::assertNotNull($assetInfo->sellDeliveryMethods);
        self::assertCount(3, $assetInfo->sellDeliveryMethods);
        $sellMethod = $assetInfo->sellDeliveryMethods[0];
        self::assertEquals('cash', $sellMethod->name);
        self::assertEquals('Deposit cash BRL at one of our agent locations.', $sellMethod->description);
        $sellMethod = $assetInfo->sellDeliveryMethods[1];
        self::assertEquals('ACH', $sellMethod->name);
        self::assertEquals("Send BRL directly to the Anchor's bank account.", $sellMethod->description);
        $sellMethod = $assetInfo->sellDeliveryMethods[2];
        self::assertEquals('PIX', $sellMethod->name);
        self::assertEquals("Send BRL directly to the Anchor's bank account.", $sellMethod->description);

        self::assertNotNull($assetInfo->buyDeliveryMethods);
        self::assertCount(3, $assetInfo->buyDeliveryMethods);
        $buyMethod = $assetInfo->buyDeliveryMethods[0];
        self::assertEquals('cash', $buyMethod->name);
        self::assertEquals('Pick up cash BRL at one of our payout locations.', $buyMethod->description);
        $buyMethod = $assetInfo->buyDeliveryMethods[1];
        self::assertEquals('ACH', $buyMethod->name);
        self::assertEquals('Have BRL sent directly to your bank account.', $buyMethod->description);
        $buyMethod = $assetInfo->buyDeliveryMethods[2];
        self::assertEquals('PIX', $buyMethod->name);
        self::assertEquals('Have BRL sent directly to the account of your choice.', $buyMethod->description);
    }

    public function testGetPrices(): void
    {
        $integration = new QuotesIntegration();
        $sep38Service = new Sep38Service(sep38Integration: $integration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'sell_amount' => '100.0',
            'country_code' => 'BRA',
            'buy_delivery_method' => 'ACH',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->pricesEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $prices = $this->getSep38PricesResponse($response);
        self::assertCount(1, $prices->buyAssets);
        $buyAsset = $prices->buyAssets[0];
        self::assertEquals(QuotesIntegration::$iso4217BRLStr, $buyAsset->asset);
        self::assertEquals('0.18', $buyAsset->price);
        self::assertEquals(2, $buyAsset->decimals);

        $data = [
            'sell_asset' => QuotesIntegration::$iso4217BRLStr,
            'sell_amount' => '500',
            'country_code' => 'BRA',
            'sell_delivery_method' => 'PIX',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->pricesEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $prices = $this->getSep38PricesResponse($response);
        self::assertCount(1, $prices->buyAssets);
        $buyAsset = $prices->buyAssets[0];
        self::assertEquals(QuotesIntegration::$stellarUSDCStr, $buyAsset->asset);
        self::assertEquals('5.42', $buyAsset->price);
        self::assertEquals(7, $buyAsset->decimals);

        // check errors
        $data = [
            'sell_amount' => '500',
            'country_code' => 'BRA',
            'sell_delivery_method' => 'PIX',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->pricesEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'sell_asset is required');

        $data = [
            'sell_asset' => 'XLM',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->pricesEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'sell_asset has an invalid format');

        $data = [
            'sell_asset' => 'iso4217:USD',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->pricesEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'sell_asset is not supported');

        $data = [
            'sell_asset' => QuotesIntegration::$iso4217BRLStr,
            'sell_amount' => 'Magic',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->pricesEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'sell_amount must be a float');

        $data = [
            'sell_asset' => QuotesIntegration::$iso4217BRLStr,
            'sell_amount' => '-100.12',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->pricesEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'sell_amount must be greater than zero');

        $data = [
            'sell_asset' => QuotesIntegration::$iso4217BRLStr,
            'sell_amount' => '500',
            'country_code' => 'RO',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->pricesEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Unsupported country code');

        $data = [
            'sell_asset' => QuotesIntegration::$iso4217BRLStr,
            'sell_amount' => '500',
            'country_code' => 'BRA',
            'sell_delivery_method' => 'SEPA',
        ];

        $request = ServerRequestBuilder::getServerRequest($this->pricesEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Unsupported sell_delivery_method');
    }

    public function testGetPrice(): void
    {
        $integration = new QuotesIntegration();
        $sep38Service = new Sep38Service(sep38Integration: $integration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $data = [
            'sell_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_asset' => QuotesIntegration::$stellarUSDCStr,
            'sell_amount' => '500',
            'sell_delivery_method' => 'PIX',
            'country_code' => 'BRA',
            'context' => 'sep6',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $price = $this->getSep38PriceResponse($response);
        self::assertEquals('5.42', $price->totalPrice);
        self::assertEquals('5.00', $price->price);
        self::assertEquals('542', $price->sellAmount);
        self::assertEquals('100', $price->buyAmount);
        $fee = $price->fee;
        self::assertEquals('42.00', $fee->total);
        self::assertEquals(QuotesIntegration::$iso4217BRLStr, $fee->asset);

        $data = [
            'sell_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_amount' => '100',
            'sell_delivery_method' => 'PIX',
            'country_code' => 'BRA',
            'context' => 'sep31',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $price = $this->getSep38PriceResponse($response);
        self::assertEquals('5.42', $price->totalPrice);
        self::assertEquals('5.00', $price->price);
        self::assertEquals('542', $price->sellAmount);
        self::assertEquals('100', $price->buyAmount);
        $fee = $price->fee;
        self::assertEquals('8.40', $fee->total);
        self::assertEquals(QuotesIntegration::$stellarUSDCStr, $fee->asset);
        $feeDetails = $fee->details;
        self::assertNotNull($feeDetails);
        self::assertCount(1, $feeDetails);
        $feeDetail = $feeDetails[0];
        self::assertEquals('Service fee', $feeDetail->name);
        self::assertEquals('8.40', $feeDetail->amount);

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
            'sell_amount' => '90',
            'buy_delivery_method' => 'PIX',
            'country_code' => 'BRA',
            'context' => 'sep6',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $price = $this->getSep38PriceResponse($response);
        self::assertEquals('0.20', $price->totalPrice);
        self::assertEquals('0.18', $price->price);
        self::assertEquals('100', $price->sellAmount);
        self::assertEquals('500', $price->buyAmount);
        $fee = $price->fee;
        self::assertEquals('55.5556', $fee->total);
        self::assertEquals(QuotesIntegration::$iso4217BRLStr, $fee->asset);
        $feeDetails = $fee->details;
        self::assertNotNull($feeDetails);
        self::assertCount(1, $feeDetails);
        $feeDetail = $feeDetails[0];
        self::assertEquals('PIX fee', $feeDetail->name);
        self::assertEquals('55.5556', $feeDetail->amount);
        self::assertEquals(
            'Fee charged in order to process the outgoing PIX transaction.',
            $feeDetail->description,
        );

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_amount' => '500',
            'buy_delivery_method' => 'PIX',
            'country_code' => 'BRA',
            'context' => 'sep31',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $price = $this->getSep38PriceResponse($response);
        self::assertEquals('0.20', $price->totalPrice);
        self::assertEquals('0.18', $price->price);
        self::assertEquals('100', $price->sellAmount);
        self::assertEquals('500', $price->buyAmount);
        $fee = $price->fee;
        self::assertEquals('10.00', $fee->total);
        self::assertEquals(QuotesIntegration::$stellarUSDCStr, $fee->asset);
        $feeDetails = $fee->details;
        self::assertNotNull($feeDetails);
        self::assertCount(2, $feeDetails);
        $feeDetail = $feeDetails[0];
        self::assertEquals('Service fee', $feeDetail->name);
        self::assertEquals('5.00', $feeDetail->amount);
        $feeDetail = $feeDetails[1];
        self::assertEquals('PIX fee', $feeDetail->name);
        self::assertEquals('5.00', $feeDetail->amount);
        self::assertEquals(
            'Fee charged in order to process the outgoing BRL PIX transaction.',
            $feeDetail->description,
        );

        // check errors
        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
        ];
        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'buy_asset is required');

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => 'Magic',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'buy_asset has an invalid format');

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
        ];
        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'either sell_amount or buy_amount must be provided, but not both.');

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_amount' => '200',
            'sell_amount' => '200',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'either sell_amount or buy_amount must be provided, but not both.');

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_amount' => 'Magic',
        ];
        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'buy_amount must be a float');

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_amount' => '500',
            'buy_delivery_method' => 'PIX',
            'country_code' => 'BRA',
        ];

        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'context is required');

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_amount' => '500',
            'buy_delivery_method' => 'PIX',
            'country_code' => 'BRA',
            'context' => 'sep24',
        ];

        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'context must be one of sep6, sep31');

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_amount' => '500',
            'buy_delivery_method' => 'PIX',
            'country_code' => 'RO',
            'context' => 'sep31',
        ];

        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Unsupported country code');

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_amount' => '500',
            'buy_delivery_method' => 'Magic',
            'country_code' => 'BRA',
            'context' => 'sep31',
        ];

        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Unsupported buy_delivery_method');

        $data = [
            'sell_asset' => QuotesIntegration::$stellarUSDCStr,
            'buy_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_amount' => '500',
            'sell_delivery_method' => 'Magic',
            'buy_delivery_method' => 'PIX',
            'country_code' => 'BRA',
            'context' => 'sep31',
        ];

        $request = ServerRequestBuilder::getServerRequest($this->priceEndpoint, $data);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 400, 'Unsupported sell_delivery_method');
    }

    public function testPostQuote(): void
    {
        $integration = new QuotesIntegration();
        $sep38Service = new Sep38Service(sep38Integration: $integration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $data = [
            'sell_asset' => QuotesIntegration::$iso4217BRLStr,
            'buy_asset' => QuotesIntegration::$stellarUSDCStr,
            'sell_amount' => '542',
            'expire_after' => '2025-04-30T07:42:23Z',
            'country_code' => 'BRA',
            'context' => 'sep31',
        ];
        $request = $this->postServerRequest($data, $this->quoteEndpoint);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $quote = $this->getSep38PostQuoteResponse($response);
        self::assertEquals('de762cda-a193-4961-861e-57b31fed6eb3', $quote->id);
        self::assertEquals(
            DateTime::createFromFormat(
                DATE_ATOM,
                '2025-04-30T07:42:23Z',
            ),
            $quote->expiresAt,
        );
        self::assertEquals('5.42', $quote->totalPrice);
        self::assertEquals('5.00', $quote->price);
        self::assertEquals(QuotesIntegration::$iso4217BRLStr, $quote->sellAsset);
        self::assertEquals('542', $quote->sellAmount);
        self::assertEquals(QuotesIntegration::$stellarUSDCStr, $quote->buyAsset);
        self::assertEquals('100', $quote->buyAmount);
        $fee = $quote->fee;
        self::assertEquals('42.00', $fee->total);
        self::assertEquals(QuotesIntegration::$iso4217BRLStr, $fee->asset);
        $feeDetails = $fee->details;
        self::assertNotNull($feeDetails);
        self::assertCount(3, $feeDetails);
        $feeDetail = $feeDetails[0];
        self::assertEquals('PIX fee', $feeDetail->name);
        self::assertEquals('12.00', $feeDetail->amount);
        self::assertEquals(
            'Fee charged in order to process the outgoing PIX transaction.',
            $feeDetail->description,
        );
        $feeDetail = $feeDetails[1];
        self::assertEquals('Brazilian conciliation fee', $feeDetail->name);
        self::assertEquals('15.00', $feeDetail->amount);
        self::assertEquals(
            'Fee charged in order to process conciliation costs with intermediary banks.',
            $feeDetail->description,
        );
        $feeDetail = $feeDetails[2];
        self::assertEquals('Service fee', $feeDetail->name);
        self::assertEquals('15.00', $feeDetail->amount);
    }

    public function testGetQuote(): void
    {
        $integration = new QuotesIntegration();
        $sep38Service = new Sep38Service(sep38Integration: $integration);
        $sep10Jwt = $this->createSep10Jwt($this->accountId);
        $url = $this->quoteEndpoint . '/de762cda-a193-4961-861e-57b31fed6eb3';
        $request = ServerRequestBuilder::getServerRequest($url, []);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $quote = $this->getSep38GetQuoteResponse($response);
        self::assertEquals('de762cda-a193-4961-861e-57b31fed6eb3', $quote->id);

        // check error
        $url = $this->quoteEndpoint . '/magic';
        $request = ServerRequestBuilder::getServerRequest($url, []);
        $response = $sep38Service->handleRequest($request, $sep10Jwt);
        $this->checkError($response, 404, 'quote not found for id: magic');
    }

    private function getSep38InfoResponse(ResponseInterface $response): SEP38InfoResponse
    {
        self::assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP38InfoResponse::fromJson($decoded);
    }

    private function getSep38PricesResponse(ResponseInterface $response): SEP38PricesResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP38PricesResponse::fromJson($decoded);
    }

    private function getSep38PriceResponse(ResponseInterface $response): SEP38PriceResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP38PriceResponse::fromJson($decoded);
    }

    private function getSep38PostQuoteResponse(ResponseInterface $response): SEP38QuoteResponse
    {
        assertEquals(201, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP38QuoteResponse::fromJson($decoded);
    }

    private function getSep38GetQuoteResponse(ResponseInterface $response): SEP38QuoteResponse
    {
        assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));

        return SEP38QuoteResponse::fromJson($decoded);
    }

    private function checkError(ResponseInterface $response, int $statusCode, string $message): void
    {
        assertEquals($statusCode, $response->getStatusCode());
        $decoded = json_decode($response->getBody()->__toString(), true);
        assert(is_array($decoded));
        assertTrue(is_string($decoded['error']));
        $errorMsg = $decoded['error'];
        assertEquals($message, $errorMsg);
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

    /**
     * Creates a post server request.
     *
     * @param array<string, mixed> $parameters params
     * @param string $uri uri
     *
     * @return ServerRequestInterface the request.
     */
    private function postServerRequest(
        array $parameters,
        string $uri,
    ): ServerRequestInterface {
        return ServerRequestBuilder::serverRequest(
            method: 'POST',
            parameters: $parameters,
            uri: $uri,
            contentType: ServerRequestBuilder::CONTENT_TYPE_APPLICATION_JSON,
        );
    }
}
