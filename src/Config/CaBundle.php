<?php

declare(strict_types=1);

namespace Jacomeit\LexwarePhpMcp\Config;

/**
 * Finds trusted root certificates for HTTPS to the Lexware API.
 *
 * The standalone binaries (static PHP) ship OpenSSL without a CA bundle and only look at the
 * compile-time default path /etc/ssl/cert.pem. That file exists on macOS but not on Debian,
 * Ubuntu, Fedora or Windows, so TLS would fail there. A regular PHP installation already
 * finds its certificates and is left alone.
 */
final class CaBundle
{
    /** Known system bundles, most common first. */
    public const array CANDIDATES = [
        '/etc/ssl/certs/ca-certificates.crt', // Debian, Ubuntu, Alpine
        '/etc/pki/tls/certs/ca-bundle.crt',   // Fedora, RHEL
        '/etc/ssl/ca-bundle.pem',             // openSUSE
        '/etc/ssl/cert.pem',                  // macOS, Arch
    ];

    /**
     * @return array<string, mixed> options for Symfony HttpClient
     */
    public static function detectHttpClientOptions(): array
    {
        $defaultCertFile = \function_exists('openssl_get_cert_locations')
            ? openssl_get_cert_locations()['default_cert_file']
            : '';

        return self::httpClientOptions(
            \PHP_OS_FAMILY,
            getenv(),
            $defaultCertFile,
            ini_get('curl.cainfo') . ini_get('openssl.cafile'),
            is_file(...),
        );
    }

    /**
     * @param array<string, string>  $environment
     * @param \Closure(string): bool $isFile
     *
     * @return array<string, mixed> options for Symfony HttpClient
     */
    public static function httpClientOptions(
        string $osFamily,
        array $environment,
        string $defaultCertFile,
        string $configuredCaFile,
        \Closure $isFile,
    ): array {
        $userChoice = ($environment['SSL_CERT_FILE'] ?? '') . $configuredCaFile;
        if ('' !== $userChoice || ('' !== $defaultCertFile && $isFile($defaultCertFile))) {
            return [];
        }

        if ('Windows' === $osFamily) {
            return ['extra' => ['curl' => [\CURLOPT_SSL_OPTIONS => \CURLSSLOPT_NATIVE_CA]]];
        }

        foreach (self::CANDIDATES as $candidate) {
            if ($isFile($candidate)) {
                return ['cafile' => $candidate];
            }
        }

        return [];
    }
}
