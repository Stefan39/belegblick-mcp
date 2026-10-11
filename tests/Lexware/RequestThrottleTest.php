<?php

declare(strict_types=1);

namespace Jacomeit\LexwarePhpMcp\Tests\Lexware;

use Jacomeit\LexwarePhpMcp\Lexware\RequestThrottle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class RequestThrottleTest extends TestCase
{
    public function testFirstRequestDoesNotWait(): void
    {
        $clock = new MockClock('2026-10-10 12:00:00.000000');

        new RequestThrottle($clock)->wait();

        self::assertSame('12:00:00.000000', $clock->now()->format('H:i:s.u'));
    }

    public function testBackToBackRequestsAreSpacedToTwoPerSecond(): void
    {
        $clock = new MockClock('2026-10-10 12:00:00.000000');
        $throttle = new RequestThrottle($clock);

        $throttle->wait();
        $clock->sleep(0.1);
        $throttle->wait();

        self::assertSame('12:00:00.500000', $clock->now()->format('H:i:s.u'));
    }

    public function testNoWaitWhenIntervalAlreadyElapsed(): void
    {
        $clock = new MockClock('2026-10-10 12:00:00.000000');
        $throttle = new RequestThrottle($clock);

        $throttle->wait();
        $clock->sleep(2);
        $throttle->wait();

        self::assertSame('12:00:02.000000', $clock->now()->format('H:i:s.u'));
    }
}
