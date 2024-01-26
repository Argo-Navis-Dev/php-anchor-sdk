<?php

declare(strict_types=1);

namespace ArgoNavis\Test\PhpAnchorSdk;

use Laminas\Diactoros\Stream;
use Ramsey\Dev\Tools\TestCase as BaseTestCase;

use function assert;
use function is_string;
use function json_encode;

/**
 * A base test case for common test functionality
 */
class TestCase extends BaseTestCase
{
    /**
     * @param array<array-key, mixed> $data
     */
    protected function getStreamFromDataArray(array $data): Stream
    {
        $stream = new Stream('php://temp', 'w+');
        $jsonData = json_encode($data);
        assert(is_string($jsonData));
        $stream->write($jsonData);
        $stream->rewind();

        return $stream;
    }
}
