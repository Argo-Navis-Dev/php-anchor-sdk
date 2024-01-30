<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep12;

use ArgoNavis\PhpAnchorSdk\exception\InvalidRequestData;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

use function array_intersect;
use function count;
use function explode;
use function getimagesizefromstring;
use function is_array;
use function is_string;
use function mb_strlen;
use function preg_match;
use function preg_quote;
use function preg_split;
use function str_contains;
use function str_starts_with;
use function stripos;
use function strlen;
use function strtolower;
use function trim;

use const PREG_SPLIT_NO_EMPTY;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_OK;

class MultipartFormDataParser
{
    /**
     * @var array<string> $knownFileFields known sep-09 field names that represent files (images).
     */
    private array $knownFileFields = ['photo_id_front',
        'photo_id_back',
        'notary_approval_of_photo_id',
        'photo_proof_residence',
        'proof_of_income',
        'proof_of_liveness',
        'organization.photo_incorporation_doc',
        'organization.photo_proof_address',
    ];

    /**
     * @var int upload file max size in bytes.
     */
    private int $uploadFileMaxSize;

    /**
     * @var int maximum upload files count.
     */
    private int $uploadFileMaxCount;

    /**
     * @param int $uploadFileMaxSize maximal size of a file that can be uploaded.
     * @param int $uploadFileMaxCount maximal number of files that can be uploaded.
     */
    public function __construct(int $uploadFileMaxSize, int $uploadFileMaxCount)
    {
        $this->uploadFileMaxSize = $uploadFileMaxSize;
        $this->uploadFileMaxCount = $uploadFileMaxCount;
    }

    /**
     * Parses given request in case it holds 'multipart/form-data' content.
     *
     * @return MultipartFormDataset containing the parsed data.
     *
     * @throws InvalidRequestData if not 'multipart/form-data' or could not be parsed.
     */
    public function parse(RequestInterface $request): MultipartFormDataset
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (strlen($contentType) === 0) {
            throw new InvalidRequestData('no content type in header');
        }
        if (stripos($contentType, 'multipart/form-data') === false) {
            throw new InvalidRequestData('content type is not multipart/form-data');
        }
        if (!preg_match('/boundary=(.*)$/is', $contentType, $matches)) {
            throw new InvalidRequestData('could not parse boundary from header');
        }

        $boundary = $matches[1];

        $rawBody = $request->getBody()->__toString();
        // $rawBody = file_get_contents('/Users/body.txt', false);
        //file_put_contents('/Users/body.txt', $rawBody);
        if (strlen($rawBody) === 0) {
            throw new InvalidRequestData('body is empty');
        }

        $bodyParts = preg_split('/\\R?-+' . preg_quote($boundary, '/') . '/s', $rawBody);
        if (!$bodyParts) {
            throw new InvalidRequestData('body parts not found');
        }

        /**
         * @var array<string, string> $bodyParams
         */
        $bodyParams = [];
        /**
         * @var array<string, UploadedFileInterface> $uploadedFiles
         */
        $uploadedFiles = [];
        $filesCount = 0;
        foreach ($bodyParts as $bodyPart) {
            if (strlen($bodyPart) === 0 || str_starts_with($bodyPart, '--')) {
                continue;
            }
            $split = preg_split('/\\R\\R/', $bodyPart, 2);
            if (!is_array($split)) {
                continue;
            }
            [$headers, $value] = $split;
            $headers = $this->parseHeaders($headers);
            if (!isset($headers['content-disposition']['name'])) {
                continue;
            }
            $unparsedName = $headers['content-disposition']['name'];
            $nameParts = $this->parseNameParts($unparsedName);
            $clientFilename = null;
            $imageContentType = null;
            if (isset($headers['content-disposition']['filename'])) {
                $clientFilename = $headers['content-disposition']['filename'];
            } else {
                // sometimes filename is missing but the value represents a file
                // this will check against known images.
                $commonElements = array_intersect($nameParts, $this->knownFileFields);
                if (count($commonElements) > 0) {
                    $fieldName = $commonElements[0];
                    $imageType = $this->isImage($value);
                    if (is_string($imageType)) {
                        $clientFilename = $fieldName . '.' . $imageType;
                        $imageContentType = 'image/' . $imageType;
                    }
                }
            }
            if ($clientFilename !== null) {
                // file upload:
                if ($filesCount >= $this->uploadFileMaxCount) {
                    continue;
                }

                $clientMediaType = 'application/octet-stream';
                if (isset($headers['content-type']) && is_string($headers['content-type'])) {
                    $clientMediaType = $headers['content-type'];
                } elseif ($imageContentType !== null) {
                    $clientMediaType = $imageContentType;
                }
                $size = mb_strlen($value, '8bit');
                $error = UPLOAD_ERR_OK;
                $streamFactory = new StreamFactory();
                if ($size > $this->uploadFileMaxSize) {
                    $error = UPLOAD_ERR_INI_SIZE;
                    $stream = $streamFactory->createStream('');
                } else {
                    try {
                        $stream = $streamFactory->createStream($value);
                    } catch (Throwable) {
                        $error = UPLOAD_ERR_CANT_WRITE;
                        $stream = $streamFactory->createStream('');
                    }
                }

                foreach ($nameParts as $part) {
                    $uploadedFiles[$part] = $this->createUploadedFile(
                        $stream,
                        $error,
                        $size,
                        $clientFilename,
                        $clientMediaType,
                    );
                }

                $filesCount++;
            } else {
                foreach ($nameParts as $part) {
                    $bodyParams[$part] = trim($value);
                }
            }
        }

        return new MultipartFormDataset($bodyParams, $uploadedFiles);
    }

    private function isImage(string $byteString): string | false
    {
        $imageInfo = getimagesizefromstring($byteString);
        if ($imageInfo !== false) {
            $mimeType = $imageInfo['mime'];
            if (str_starts_with($mimeType, 'image/')) {
                $array = explode('/', $mimeType);
                if (count($array) > 1) {
                    return $array[1];
                }
            }
        }

        return false;
    }

    /**
     * Creates new uploaded file instance.
     *
     * @param StreamInterface $dataStream the stream containing the data of the file.
     * @param int $errorStatus the error associated with the uploaded file.
     * @param int $size size of the file.
     * @param string $clientFilename the filename sent by the client.
     * @param string|null $clientMediaType the media type sent by the client.
     *
     * @return UploadedFileInterface the uploaded file.
     */
    protected function createUploadedFile(
        StreamInterface $dataStream,
        int $errorStatus,
        int $size,
        string $clientFilename,
        ?string $clientMediaType = null,
    ): UploadedFileInterface {
        return new UploadedFile(
            streamOrFile: $dataStream,
            size: $size,
            errorStatus: $errorStatus,
            clientFilename: $clientFilename,
            clientMediaType: $clientMediaType,
        );
    }

    /**
     * Parses content part headers.
     *
     * @param string $headerContent headers source content
     *
     * @return array<string, string> | array<string, array<string>> | array<string, array<string, string>> parsed headers.
     *
     * @throws InvalidRequestData
     */
    private function parseHeaders(string $headerContent): array
    {
        $headers = [];
        $headerParts = preg_split('/\\R/s', $headerContent, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($headerParts)) {
            throw new InvalidRequestData('could not split header');
        }
        foreach ($headerParts as $headerPart) {
            if (!str_contains($headerPart, ':')) {
                continue;
            }
            $split = explode(':', $headerPart, 2);
            if (count($split) !== 2) {
                continue;
            }
            [$headerName, $headerValue] = $split;
            $headerName = strtolower(trim($headerName));
            $headerValue = trim($headerValue);

            if (!str_contains($headerValue, ';')) {
                $headers[$headerName] = $headerValue;
            } else {
                /**
                 * @var array<string | string,string> $parts
                 */
                $parts = [];
                foreach (explode(';', $headerValue) as $part) {
                    $part = trim($part);
                    if (!str_contains($part, '=')) {
                        $parts[] = $part;
                    } else {
                        $nvSplit = explode('=', $part, 2);
                        if (count($nvSplit) !== 2) {
                            continue;
                        }
                        [$name, $value] = $nvSplit;
                        $name = strtolower(trim($name));
                        $value = trim(trim($value), '"');
                        $parts[$name] = $value;
                    }
                }
                $headers[$headerName] = $parts;
            }
        }

        return $headers;
    }

    /**
     * @param string $name name that may be split in multiple parts.
     *
     * @return array<string> the parts.
     */
    private function parseNameParts(string $name): array
    {
        /**
         * @var array<string> $result the return value.
         */
        $result = [];
        $nameParts = preg_split('/\\]\\[|\\[/s', $name);
        if (!is_array($nameParts)) {
            $result[] = $name;

            return $result;
        }
        foreach ($nameParts as $namePart) {
            $namePart = trim($namePart, ']');
            if ($namePart !== '') {
                $result[] = $namePart;
            }
        }

        return $result;
    }
}
