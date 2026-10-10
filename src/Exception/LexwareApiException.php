<?php

declare(strict_types=1);

namespace Jacomeit\Belegblick\Exception;

final class LexwareApiException extends \RuntimeException
{
    private const array HINTS = [
        401 => 'the API key was rejected, check LEXWARE_API_KEY',
        403 => 'the API key lacks permission for this resource',
        404 => 'resource not found',
        429 => 'rate limit (2 requests/second) still exceeded after retries',
    ];

    private function __construct(string $message, public readonly ?int $statusCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function fromStatus(int $statusCode, string $path): self
    {
        $hint = self::HINTS[$statusCode] ?? ($statusCode >= 500 ? 'Lexware Office is currently unavailable' : 'unexpected response');

        return new self(\sprintf('Lexware Office API returned HTTP %d for %s: %s.', $statusCode, $path, $hint), $statusCode);
    }

    public static function transportFailure(string $path, \Throwable $previous): self
    {
        return new self(\sprintf('Could not reach Lexware Office API for %s: %s', $path, $previous->getMessage()), null, $previous);
    }

    public static function invalidResponse(string $path, \Throwable $previous): self
    {
        return new self(\sprintf('Lexware Office API returned an invalid JSON body for %s.', $path), null, $previous);
    }
}
