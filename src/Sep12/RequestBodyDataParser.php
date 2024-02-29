<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep12;

use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function json_decode;
use function parse_str;
use function str_starts_with;
use function strlen;

class RequestBodyDataParser
{
    /**
     * Parses the data from the request. It supports following content types:
     * - application/x-www-form-urlencoded
     * - multipart/form-data
     * - application/json
     *
     * @param ServerRequestInterface $request the request
     * @param int $uploadFileMaxSize the maximum size of a file to be uploaded in bytes.
     * @param int $uploadFileMaxCount the maximum number of allowed files to be uploaded.
     *
     * @return array<array-key, mixed> | MultipartFormDataset the parsed body data
     *
     * @throws InvalidRequestData if the body data could not be parsed.
     */
    public static function getParsedBodyData(
        ServerRequestInterface $request,
        int $uploadFileMaxSize,
        int $uploadFileMaxCount,
    ): array | MultipartFormDataset {
        $content = $request->getBody()->__toString();
        if (strlen($content) === 0) {
            return [];
        }

        $contentType = $request->getHeaderLine('Content-Type');
        if ($contentType === 'application/x-www-form-urlencoded') {
            parse_str($content, $parsedArray);

            return $parsedArray;
        } elseif (str_starts_with($contentType, 'multipart/form-data')) {
            // we have to implement an own parser for put requests.
            // see: https://bugs.php.net/bug.php?id=55815
            $parser = new MultipartFormDataParser($uploadFileMaxSize, $uploadFileMaxCount);
            try {
                return $parser->parse($request);
            } catch (InvalidRequestData $invalid) {
                throw new InvalidRequestData('Could not parse multipart/form-data : ' . $invalid->getMessage());
            }
        } elseif ($contentType === 'application/json') {
            return self::jsonDataFromRequestString($content);
        } else {
            throw new InvalidRequestData('Invalid request type ' . $contentType);
        }
    }

    /**
     * Parses the json data from the body if the request is of type application/json.
     *
     * @param string $content the body of the request.
     *
     * @return array<array-key, mixed> the parsed data.
     *
     * @throws InvalidRequestData if the data is not json or invalid json
     */
    private static function jsonDataFromRequestString(string $content): array
    {
        $jsonData = @json_decode($content, true);
        if ($jsonData === null) {
            return [];
        }
        if (!is_array($jsonData)) {
            throw new InvalidRequestData('Invalid body.');
        }

        return $jsonData;
    }
}
