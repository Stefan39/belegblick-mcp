<?php

declare(strict_types=1);

namespace Jacomeit\LexwarePhpMcp\Lexware;

use Jacomeit\LexwarePhpMcp\Exception\LexwareApiException;

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
