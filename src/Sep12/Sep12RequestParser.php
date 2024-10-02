<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep12;

use ArgoNavis\PhpAnchorSdk\Sep10\Sep10Jwt;
use ArgoNavis\PhpAnchorSdk\callback\GetCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerCallbackRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerVerificationRequest;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;
use ArgoNavis\PhpAnchorSdk\logging\NullLogger;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;

use function array_key_exists;
use function array_keys;
use function filter_var;
use function intval;
use function is_int;
use function is_numeric;
use function is_string;
use function json_encode;
use function str_ends_with;

use const FILTER_VALIDATE_URL;

class Sep12RequestParser
{
    /**
     * The PSR-3 specific logger to be used for logging.
     */
    private static LoggerInterface | NullLogger | null $logger;

    /**
     * Parses and validates the base request data.
     *
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     *
     * @return Sep12CustomerRequestBase the parsed and validated base request data.
     *
     * @throws InvalidSepRequest if the data is invalid.
     */
    public static function getBaseFromRequestData(array $requestData): Sep12CustomerRequestBase
    {
        $id = null;
        if (isset($requestData['id'])) {
            if (is_string($requestData['id'])) {
                $id = $requestData['id'];
            } else {
                throw new InvalidSepRequest('id must be a string');
            }
        }

        $account = null;
        if (isset($requestData['account'])) {
            if (is_string($requestData['account'])) {
                $account = $requestData['account'];
            } else {
                throw new InvalidSepRequest('account must be a string');
            }
        }

        if (isset($requestData['memo_type'])) {
            if (is_string($requestData['memo_type'])) {
                $memoType = $requestData['memo_type'];
                if ($memoType !== 'id') {
                    throw new InvalidSepRequest('only memo type id supported.');
                }
            } else {
                throw new InvalidSepRequest('memo_type must be a string');
            }
        }

        $memo = null;
        if (isset($requestData['memo'])) {
            if (is_string($requestData['memo'])) {
                $memoStr = $requestData['memo'];
                if (is_numeric($memoStr) && is_int($memoStr + 0)) {
                    $memo = intval($memoStr);
                } else {
                    throw new InvalidSepRequest('invalid memo value: ' . $memoStr);
                }
            } else {
                throw new InvalidSepRequest('memo must be a string');
            }
        }

        $type = null;
        if (isset($requestData['type'])) {
            if (is_string($requestData['type'])) {
                $type = $requestData['type'];
            } else {
                throw new InvalidSepRequest('type must be a string');
            }
        }
        $sep12CustomerRequestBase = new Sep12CustomerRequestBase(
            $id,
            $account,
            $memo,
            $type,
        );
        self::getLogger()->debug(
            'The base parameters after processing',
            ['context' => 'sep12', 'parameters' => json_encode($sep12CustomerRequestBase)],
        );

        return $sep12CustomerRequestBase;
    }

    /**
     * Parses and validates the request data and composes a GetCustomerRequest from it.
     *
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     * @param Sep10Jwt $token the token obtained via sep-10.
     *
     * @throws InvalidSepRequest if the data is invalid.
     */
    public static function getCustomerRequestFromRequestData(array $requestData, Sep10Jwt $token): GetCustomerRequest
    {
        $base = self::getBaseFromRequestData($requestData);

        $lang = null;
        if (isset($requestData['lang'])) {
            if (is_string($requestData['lang'])) {
                $lang = $requestData['lang'];
            } else {
                throw new InvalidSepRequest('lang must be a string');
            }
        }

        if ($base->account === null) {
            $base->account = $token->muxedAccountId;
            if ($base->account === null) {
                $base->account = $token->accountId;
            }
        }

        if ($base->account === null) {
            throw new InvalidSepRequest('invalid jwt token');
        }
        $getCustomerRequest = new GetCustomerRequest(
            $base->account,
            $base->memo,
            $base->id,
            $base->type,
            $lang,
        );
        self::getLogger()->debug(
            'Get customer parameters after processing',
            ['context' => 'sep12', 'parameters' => json_encode($getCustomerRequest)],
        );

        return $getCustomerRequest;
    }

    /**
     * Parses and validates the put customer request data.
     *
     * @param Sep10Jwt $token the token obtained via sep-10.
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     * @param array<string, UploadedFileInterface>|null $uploadedFiles the array of uploaded files by the client.
     *
     * @return PutCustomerRequest the parsed and validated data.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function putCustomerRequestFormRequestData(
        Sep10Jwt $token,
        array $requestData,
        ?array $uploadedFiles = null,
    ): PutCustomerRequest {
        $base = self::getBaseFromRequestData($requestData);
        if ($base->account === null) {
            $base->account = $token->muxedAccountId;
            if ($base->account === null) {
                $base->account = $token->accountId;
            }
            if ($base->account === null) {
                throw new InvalidSepRequest('invalid jwt token');
            }
        }
        $result = new PutCustomerRequest(
            $base->account,
            $base->memo,
            $base->id,
            $base->type,
        );
        $additionalData = $requestData;
        if (array_key_exists('id', $additionalData)) {
            unset($additionalData['id']);
        }
        if (array_key_exists('account', $additionalData)) {
            unset($additionalData['account']);
        }
        if (array_key_exists('memo', $additionalData)) {
            unset($additionalData['memo']);
        }
        if (array_key_exists('memoType', $additionalData)) {
            unset($additionalData['memoType']);
        }
        if (array_key_exists('type', $additionalData)) {
            unset($additionalData['type']);
        }

        $result->kycFields = $additionalData;
        $result->kycUploadedFiles = $uploadedFiles;
        self::getLogger()->debug(
            'Put customer parameters after processing',
            ['context' => 'sep12', 'parameters' => json_encode($result)],
        );

        return $result;
    }

    /**
     * Parses and validates the customer callback request data.
     *
     * @param Sep10Jwt $token the token obtained via sep-10.
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     *
     * @return PutCustomerCallbackRequest the parsed and validated data.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function putCustomerCallbackRequestFormRequestData(
        Sep10Jwt $token,
        array $requestData,
    ): PutCustomerCallbackRequest {
        $base = self::getBaseFromRequestData($requestData);
        if ($base->account === null) {
            $base->account = $token->muxedAccountId;
            if ($base->account === null) {
                $base->account = $token->accountId;
            }
            if ($base->account === null) {
                throw new InvalidSepRequest('invalid jwt token');
            }
        }
        $url = null;
        if (isset($requestData['url'])) {
            if (is_string($requestData['url'])) {
                $url = $requestData['url'];
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new InvalidSepRequest('invalid url');
                }
            } else {
                throw new InvalidSepRequest('url must be a string');
            }
        }

        $putCustomerCallbackRequest = new PutCustomerCallbackRequest(
            $base->account,
            $base->memo,
            $base->id,
            $url,
        );
        self::getLogger()->debug(
            'Put customer callback parameters after processing',
            ['context' => 'sep12', 'parameters' => json_encode($putCustomerCallbackRequest)],
        );

        return $putCustomerCallbackRequest;
    }

    /**
     * Parses and validates the customer verification request data.
     *
     * @param Sep10Jwt $token the token obtained via sep-10.
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     *
     * @return PutCustomerVerificationRequest the parsed and validated data.
     *
     * @throws InvalidSepRequest if the request data is invalid.
     */
    public static function putCustomerVerificationRequestFormRequestData(
        Sep10Jwt $token,
        array $requestData,
    ): PutCustomerVerificationRequest {
        $data = $requestData;
        $id = null;
        if (!array_key_exists('id', $data)) {
            throw new InvalidSepRequest('missing id');
        } elseif (is_string($data['id'])) {
            $id = $data['id'];
            unset($data['id']);
        } else {
            throw new InvalidSepRequest('id must be a string');
        }
        /**
         * @var array<string, string> $verificationFields
         */
        $verificationFields = [];
        foreach (array_keys($data) as $key) {
            if (!is_string($key)) {
                throw new InvalidSepRequest('key must be a string');
            }
            if (!str_ends_with($key, '_verification')) {
                throw new InvalidSepRequest('invalid key ' . $key);
            }
            $value = $data[$key];
            if (!is_string($value)) {
                throw new InvalidSepRequest('invalid value for ' . $key . '. Must be string');
            }
            $verificationFields[$key] = $value;
        }

        $account = $token->muxedAccountId;
        if ($account === null) {
            $account = $token->accountId;
        }

        if ($account === null) {
            throw new InvalidSepRequest('invalid jwt token');
        }

        $putCustomerVerificationRequest = new PutCustomerVerificationRequest(
            $id,
            $verificationFields,
            $account,
            self::tokenAccountMemoAsInt($token),
        );
        self::getLogger()->debug(
            'Put customer verification parameters after processing',
            ['context' => 'sep12', 'parameters' => json_encode($putCustomerVerificationRequest)],
        );

        return $putCustomerVerificationRequest;
    }

    /**
     * Extracts the memo value from the token if any.
     * The memo must be an integer (memo type id), otherwise it throws an exception.
     *
     * @throws InvalidSepRequest if the included memo is not an integer
     */
    public static function tokenAccountMemoAsInt(Sep10Jwt $token): ?int
    {
        if ($token->accountMemo === null) {
            self::getLogger()->debug('Account memo is null', ['context' => 'sep12']);

            return null;
        }
        $memoStr = $token->accountMemo;
        if (is_numeric($memoStr) && is_int($memoStr + 0)) {
            return intval($memoStr);
        } else {
            throw new InvalidSepRequest('invalid jwt token memo value: ' . $memoStr);
        }
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
