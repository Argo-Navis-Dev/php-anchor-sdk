<?php

declare(strict_types=1);

namespace ArgoNavis\Test\PhpAnchorSdk;

use ArgoNavis\PhpAnchorSdk\SEP\Toml\TomlData;
use ArgoNavis\PhpAnchorSdk\SEP\Toml\TomlProvider;
use GuzzleHttp\Client;

use const PHP_EOL;

class TomlTest extends TestCase
{
    public function testGreet(): void
    {
        $tomlData = TomlData::fromDomain('ultrastellar.com', new Client());
        $provider = new TomlProvider();
        $tomlResponse = $provider->handle($tomlData);
        $this->assertEquals(200, $tomlResponse->getStatusCode());
        print $tomlResponse->getBody() . PHP_EOL;

        //$example = $this->mockery(Example::class);
        //$example->shouldReceive('greet')->passthru();
        //$this->assertSame('Hello, Friends!', $example->greet('Friends'));
    }
}
