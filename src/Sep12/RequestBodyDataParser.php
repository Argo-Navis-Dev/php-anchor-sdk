<?php

declare(strict_types=1);

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
     * @param ServerRequestInterface $request the request
     * @param int $uploadFileMaxSize the maximum size of a file to be uploaded in bytes.
     * @param int $uploadFileMaxCount the maximum number of allowed files to be uploaded.
     *
     * @return array<array-key, mixed> | MultipartFormDataset the body data
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
     * @return array<array-key, mixed>
     *
     * @throws InvalidRequestData
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
