<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\Test\PhpAnchorSdk\util;

use GuzzleHttp\Psr7\Utils;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

use function array_keys;
use function assert;
use function http_build_query;
use function is_string;
use function json_encode;
use function str_starts_with;

class ServerRequestBuilder
{
    public const CONTENT_TYPE_MULTIPART_FORM_DATA = 'multipart/form-data';
    public const CONTENT_TYPE_APPLICATION_URLENCODED = 'application/x-www-form-urlencoded';
    public const CONTENT_TYPE_APPLICATION_JSON = 'application/json';

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function getServerRequest(string $requestUrl, array $queryParameters): ServerRequest
    {
        return (new ServerRequest())
            ->withMethod('GET')
            ->withUri(new Uri($requestUrl))
            ->withQueryParams($queryParameters);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param ?array<string, array<string, mixed>> $files (field_name => [filename => ... , content => ...]
     */
    public static function serverRequest(
        string $method,
        array $parameters,
        string $uri,
        string $contentType,
        ?array $files = null,
    ): ServerRequestInterface {
        $serverRequest = (new ServerRequest())
            ->withMethod($method)
            ->withUri(new Uri($uri))
            ->withAddedHeader('Content-Type', $contentType);

        if (str_starts_with($contentType, self::CONTENT_TYPE_MULTIPART_FORM_DATA)) {
            $multipartData = [];
            foreach (array_keys($parameters) as $key) {
                $arr = [];
                $arr += ['name' => $key];
                $arr += ['contents' => $parameters[$key]];
                $multipartData[] = $arr;
            }
            if ($files !== null) {
                foreach (array_keys($files) as $key) {
                    $arr = [];
                    $arr += ['name' => $key];
                    $arr += ['filename' => $files[$key]['filename']];
                    $arr += ['contents' => $files[$key]['contents']];
                    $multipartData[] = $arr;
                }
            }
            $stream = Utils::streamFor();
            $boundary = 'some_random_boundary';
            foreach ($multipartData as $data) {
                $stream = self::addMultipartData($stream, $data, $boundary);
            }

            return new \GuzzleHttp\Psr7\ServerRequest(
                $method,
                $uri,
                [
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ],
                $stream,
            );
        } elseif ($contentType === self::CONTENT_TYPE_APPLICATION_URLENCODED) {
            $queryString = http_build_query($parameters);
            $stream = Utils::streamFor($queryString);

            return new \GuzzleHttp\Psr7\ServerRequest(
                $method,
                $uri,
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                $stream,
            );
        } else {
            return $serverRequest->withBody(self::getStreamFromDataArray($parameters));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function addMultipartData(StreamInterface $stream, array $data, string $boundary): StreamInterface
    {
        $stream = Utils::streamFor($stream);
        $name = $data['name'];
        assert(is_string($name));
        $headers = [
            'Content-Disposition' => 'form-data; name="' . $name . '"',
        ];

        if (isset($data['filename'])) {
            $filename = $data['filename'];
            assert(is_string($filename));
            $headers['Content-Disposition'] .= '; filename="' . $filename . '"';
        }

        $headers['Content-Type'] = 'application/octet-stream';

        $stream->write("--$boundary\r\n");
        foreach ($headers as $headerName => $headerValue) {
            $stream->write("$headerName: $headerValue\r\n");
        }
        $stream->write("\r\n");

        $contents = $data['contents'];
        assert(is_string($contents));
        $stream->write($contents . "\r\n");

        return $stream;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function getStreamFromDataArray(array $data): Stream
    {
        $stream = new Stream('php://temp', 'w+');
        $jsonData = json_encode($data);
        assert(is_string($jsonData));
        $stream->write($jsonData);
        $stream->rewind();

        return $stream;
    }
}
