<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Stellar;

use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Soneso\StellarSDK\Crypto\KeyPair;

use function base64_encode;
use function json_encode;
use function microtime;
use function round;

class CallbackHelper
{
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

    /**
     * Sends a callback request to the given URL with the given request body data.
     * Example: for customer callback and callback POST request details please check the following link:
     * <a href="https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0012.md#callback-post-request">
     *     Customer callback & post callback details</a>
     *
     * @param string|null $callbackUrl The callback URL to send the request to.
     * @param string $serverAccountSigningKey The server account signing key.
     * @param object $requestBodyData The request body data to send.
     */
    public static function sendCallbackRequest(
        object $requestBodyData,
        string $serverAccountSigningKey,
        ?string $callbackUrl = null,
    ): void {
        if (isset($callbackUrl) && $callbackUrl !== '') {
            self::getLogger()->debug(
                'Executing callback request.',
                ['context' => 'shared', 'data' => json_encode($requestBodyData), 'callback_url' => $callbackUrl],
            );

            $signature = self::getCallbackSignatureHeader($callbackUrl, $requestBodyData, $serverAccountSigningKey);
            if ($signature === null) {
                self::getLogger()->error(
                    'Failed to compute the callback signature header.',
                    ['context' => 'shared'],
                );

                return;
            }
            $httpClient = new Client();
            try {
                $response = $httpClient->post($callbackUrl, [
                    'headers' => [
                        'Signature' => $signature,
                        'X-Stellar-Signature' => $signature, //Deprecated by the Stellar docs.
                    ],
                    'json' => $requestBodyData,
                ]);
                // Check the response status code
                if ($response->getStatusCode() === 200) {
                    self::getLogger()->debug(
                        'The callback has been executed successfully!',
                        ['context' => 'shared', 'callback_url' => $callbackUrl],
                    );
                } else {
                    self::getLogger()->error(
                        'Failed to execute the callback.',
                        ['context' => 'shared', 'http_status_code' => $response->getStatusCode(),
                            'callback_url' => $callbackUrl,
                        ],
                    );
                }
            } catch (RequestException $e) {
                $responseBody = '';
                if ($e->hasResponse()) {
                    $responseBodyWrapper = $e->getResponse();
                    if ($responseBodyWrapper !== null) {
                        $responseBody = $responseBodyWrapper->getBody();
                    }
                }
                self::getLogger()->error(
                    'Failed to execute the callback.',
                    [
                        'context' => 'shared',
                        'error' => $e->getMessage(),
                        'exception' => $e,
                        'body' => json_encode($responseBody),
                        'callback_url' => $callbackUrl,
                    ],
                );
            } catch (GuzzleException $e) {
                self::getLogger()->error(
                    'Failed to execute the callback.',
                    [
                        'context' => 'shared',
                        'error' => $e->getMessage(),
                        'exception' => $e,
                        'callback_url' => $callbackUrl,
                    ],
                );
            }
        } else {
            self::getLogger()->debug(
                'Callback URL is null, no callback execution action is needed.',
                ['context' => 'shared'],
            );
        }
    }

    /**
     * Computes the callback signature header value.
     *
     * @param string $callbackUrl The callback URL.
     * @param object $requestBodyData The request body data.
     * @param string $serverAccountSigningKey The server account signing key.
     *
     * @return string|null The computed signature header.
     */
    private static function getCallbackSignatureHeader(
        string $callbackUrl,
        object $requestBodyData,
        string $serverAccountSigningKey,
    ): string | null {
        $anchorKeys = KeyPair::fromSeed($serverAccountSigningKey);
        $currentTime = round(microtime(true));
        $signature = $currentTime . '.' . $callbackUrl . '.' . json_encode($requestBodyData);
        self::getLogger()->debug(
            'The callback header signature (plain) to be signed.',
            ['context' => 'shared', 'signature' => $signature],
        );
        $signature = $anchorKeys->sign($signature);
        if ($signature !== null) {
            $based64Signature = base64_encode($signature);
            $signatureHeader = 't=' . $currentTime . ', s=' . $based64Signature;
            self::getLogger()->debug(
                'The callback header signed signature.',
                ['context' => 'shared', 'signed_signature' => $signatureHeader],
            );

            return $signatureHeader;
        }

        return null;
    }

    /**
     * Sets the logger in static context.
     */
    public static function setLogger(?LoggerInterface $logger = null): void
    {
        self::$logger = $logger ?? new NullLogger();
    }

    /**
     * Returns the logger (initializes if null).
     */
    private static function getLogger(): LoggerInterface
    {
        if (!isset(self::$logger)) {
            self::$logger = new NullLogger();
        }

        return self::$logger;
    }
}
