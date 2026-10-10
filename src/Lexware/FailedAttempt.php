<?php

declare(strict_types=1);

namespace Jacomeit\Belegblick\Lexware;

use Jacomeit\Belegblick\Exception\LexwareApiException;

/**
 * @internal
 */
final readonly class FailedAttempt
{
    public function __construct(
        public LexwareApiException $exception,
        public ?int $statusCode,
        public ?int $retryAfter,
    ) {}
}
