<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep12;

use ArgoNavis\PhpAnchorSdk\callback\GetCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerRequest;
use ArgoNavis\PhpAnchorSdk\callback\PutCustomerVerificationRequest;
use ArgoNavis\PhpAnchorSdk\exception\InvalidSepRequest;

use function array_key_exists;
use function array_keys;
use function is_string;
use function str_ends_with;

class Sep12RequestParser
{
    /**
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     *
     * @throws InvalidSepRequest
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

        $memo = null;
        if (isset($requestData['memo'])) {
            if (is_string($requestData['memo'])) {
                $memo = $requestData['memo'];
            } else {
                throw new InvalidSepRequest('memo must be a string');
            }
        }

        $memoType = null;
        if (isset($requestData['memo_type'])) {
            if (is_string($requestData['memo_type'])) {
                $memoType = $requestData['memo_type'];
            } else {
                throw new InvalidSepRequest('memo_type must be a string');
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

        return new Sep12CustomerRequestBase(
            $id,
            $account,
            $memo,
            $memoType,
            $type,
        );
    }

    /**
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     *
     * @throws InvalidSepRequest
     */
    public static function getCustomerRequestFromRequestData(array $requestData): GetCustomerRequest
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

        return new GetCustomerRequest(
            $base->id,
            $base->account,
            $base->memo,
            $base->memoType,
            $base->type,
            $lang,
        );
    }

    /**
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     * @param array<array-key, mixed>|null $uploadedFiles the array of uploaded files by the client.
     *
     * @throws InvalidSepRequest
     */
    public static function putCustomerRequestFormRequestData(
        array $requestData,
        ?array $uploadedFiles = null,
    ): PutCustomerRequest {
        $base = self::getBaseFromRequestData($requestData);
        $result = new PutCustomerRequest(
            $base->id,
            $base->account,
            $base->memo,
            $base->memoType,
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

        return $result;
    }

    /**
     * @param array<array-key, mixed> $requestData the array to parse the data from.
     *
     * @throws InvalidSepRequest
     */
    public static function putCustomerVerificationRequestFormRequestData(
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

        return new PutCustomerVerificationRequest($id, $verificationFields);
    }
}
