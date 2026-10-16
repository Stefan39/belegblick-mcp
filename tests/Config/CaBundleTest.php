<?php

declare(strict_types=1);

namespace Jacomeit\LexwarePhpMcp\Tests\Config;

use Jacomeit\LexwarePhpMcp\Config\CaBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CaBundleTest extends TestCase
{
    /**
     * @param array<string, string> $environment
     */
    #[DataProvider('workingSetupProvider')]
    public function testLeavesWorkingSetupAlone(array $environment, string $configuredCaFile, string $existingFile): void
    {
        $options = CaBundle::httpClientOptions('Linux', $environment, '/etc/ssl/cert.pem', $configuredCaFile, self::files($existingFile));

        self::assertSame([], $options);
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function workingSetupProvider(): iterable
    {
        yield 'default cert file exists (macOS, distro PHP)' => [[], '', '/etc/ssl/cert.pem'];
        yield 'SSL_CERT_FILE set by the user' => [['SSL_CERT_FILE' => '/custom/ca.pem'], '', '/etc/ssl/certs/ca-certificates.crt'];
        yield 'curl.cainfo or openssl.cafile configured' => [[], '/custom/ca.pem', '/etc/ssl/certs/ca-certificates.crt'];
    }

    #[DataProvider('linuxBundleProvider')]
    public function testFindsDistributionBundleWhenDefaultIsMissing(string $bundle): void
    {
        $options = CaBundle::httpClientOptions('Linux', [], '/etc/ssl/cert.pem', '', self::files($bundle));

        self::assertSame(['cafile' => $bundle], $options);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function linuxBundleProvider(): iterable
    {
        yield 'Debian, Ubuntu' => ['/etc/ssl/certs/ca-certificates.crt'];
        yield 'Fedora, RHEL' => ['/etc/pki/tls/certs/ca-bundle.crt'];
        yield 'openSUSE' => ['/etc/ssl/ca-bundle.pem'];
    }

    public function testUsesWindowsCertificateStore(): void
    {
        $options = CaBundle::httpClientOptions('Windows', [], 'C:/usr/local/ssl/cert.pem', '', self::files());

        self::assertSame(['extra' => ['curl' => [\CURLOPT_SSL_OPTIONS => \CURLSSLOPT_NATIVE_CA]]], $options);
    }

    public function testGivesUpQuietlyWhenNothingIsFound(): void
    {
        self::assertSame([], CaBundle::httpClientOptions('Linux', [], '/etc/ssl/cert.pem', '', self::files()));
    }

    /**
     * @return \Closure(string): bool
     */
    private static function files(string ...$existing): \Closure
    {
        return static fn(string $path): bool => \in_array($path, $existing, true);
    }
}
