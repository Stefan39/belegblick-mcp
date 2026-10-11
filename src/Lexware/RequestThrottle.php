<?php

declare(strict_types=1);

namespace Jacomeit\LexwarePhpMcp\Lexware;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\ClockInterface as SleepingClockInterface;

/**
 * Spaces requests so the Lexware Office limit of 2 requests per second is never exceeded.
 */
final class RequestThrottle
{
    private ?float $lastRequestAt = null;

    public function __construct(
        private readonly SleepingClockInterface $clock,
        private readonly float $minIntervalSeconds = 0.5,
    ) {}

    public function wait(): void
    {
        if (null !== $this->lastRequestAt) {
            $remaining = $this->lastRequestAt + $this->minIntervalSeconds - self::seconds($this->clock);
            if ($remaining > 0) {
                $this->clock->sleep($remaining);
            }
        }

        $this->lastRequestAt = self::seconds($this->clock);
    }

    private static function seconds(ClockInterface $clock): float
    {
        return (float) $clock->now()->format('U.u');
    }
}
