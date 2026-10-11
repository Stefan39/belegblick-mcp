<?php

declare(strict_types=1);

namespace Jacomeit\LexwarePhpMcp\Config;

use Jacomeit\LexwarePhpMcp\Exception\MissingApiKeyException;

final readonly class Configuration
{
    public const string DEFAULT_BASE_URL = 'https://api.lexware.io';

    private function __construct(
        #[\SensitiveParameter]
        public string $apiKey,
        public string $baseUrl,
    ) {}

    /**
     * @param array<string, string> $environment
     */
    public static function fromEnvironment(array $environment): self
    {
        $apiKey = trim($environment['LEXWARE_API_KEY'] ?? '');
        if ('' === $apiKey) {
            throw MissingApiKeyException::create();
        }

        $baseUrl = rtrim(trim($environment['LEXWARE_API_BASE_URL'] ?? ''), '/');

        return new self($apiKey, '' === $baseUrl ? self::DEFAULT_BASE_URL : $baseUrl);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['apiKey' => '***', 'baseUrl' => $this->baseUrl];
    }
}
