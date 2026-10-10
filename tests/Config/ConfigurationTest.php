<?php

declare(strict_types=1);

namespace Jacomeit\Belegblick\Tests\Config;

use Jacomeit\Belegblick\Config\Configuration;
use Jacomeit\Belegblick\Exception\MissingApiKeyException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testReadsApiKeyAndDefaultBaseUrl(): void
    {
        $configuration = Configuration::fromEnvironment(['LEXWARE_API_KEY' => ' secret-key ']);

        self::assertSame('secret-key', $configuration->apiKey);
        self::assertSame('https://api.lexware.io', $configuration->baseUrl);
    }

    public function testCustomBaseUrlLosesTrailingSlash(): void
    {
        $configuration = Configuration::fromEnvironment([
            'LEXWARE_API_KEY' => 'secret-key',
            'LEXWARE_API_BASE_URL' => 'https://sandbox.example.test/',
        ]);

        self::assertSame('https://sandbox.example.test', $configuration->baseUrl);
    }

    /**
     * @param array<string, string> $environment
     */
    #[DataProvider('missingKeyProvider')]
    public function testMissingApiKeyIsRejected(array $environment): void
    {
        $this->expectException(MissingApiKeyException::class);

        Configuration::fromEnvironment($environment);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function missingKeyProvider(): iterable
    {
        yield 'absent' => [[]];
        yield 'blank' => [['LEXWARE_API_KEY' => '   ']];
    }

    public function testApiKeyIsNotExposedByDebugOutput(): void
    {
        $configuration = Configuration::fromEnvironment(['LEXWARE_API_KEY' => 'secret-key']);

        self::assertStringNotContainsString('secret-key', print_r($configuration, true));
        self::assertStringNotContainsString('secret-key', var_export($configuration->__debugInfo(), true));
    }
}
