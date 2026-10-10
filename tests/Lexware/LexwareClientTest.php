<?php

declare(strict_types=1);

namespace Jacomeit\Belegblick\Tests\Lexware;

use Jacomeit\Belegblick\Exception\LexwareApiException;
use Jacomeit\Belegblick\Lexware\LexwareClient;
use Jacomeit\Belegblick\Lexware\RequestThrottle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LexwareClientTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-10-10 12:00:00.000000');
    }

    public function testReturnsDecodedJsonAndSendsQuery(): void
    {
        $response = new MockResponse('{"content":[]}');
        $http = new MockHttpClient([$response], 'https://api.example.test');

        $result = $this->createClient($http)->getJson('/v1/voucherlist', ['page' => 0]);

        self::assertSame(['content' => []], $result);
        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://api.example.test/v1/voucherlist?page=0', $response->getRequestUrl());
    }

    public function testRetriesAfterRateLimitHonouringRetryAfter(): void
    {
        $http = new MockHttpClient([
            new MockResponse('', ['http_code' => 429, 'response_headers' => ['Retry-After' => '3']]),
            new MockResponse('{"ok":true}'),
        ], 'https://api.example.test');

        $result = $this->createClient($http)->getJson('/v1/voucherlist');

        self::assertSame(['ok' => true], $result);
        self::assertSame(2, $http->getRequestsCount());
        self::assertGreaterThanOrEqual(3.0, $this->elapsedSeconds());
    }

    public function testRetriesServerErrorsWithExponentialBackoffThenGivesUp(): void
    {
        $http = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('', ['http_code' => 503]),
        ], 'https://api.example.test');

        try {
            $this->createClient($http)->getJson('/v1/voucherlist');
            self::fail('Expected LexwareApiException');
        } catch (LexwareApiException $exception) {
            self::assertSame(503, $exception->statusCode);
        }

        self::assertSame(3, $http->getRequestsCount());
        self::assertGreaterThanOrEqual(3.0, $this->elapsedSeconds());
    }

    public function testUnauthorizedIsNotRetriedAndExplainsTheCause(): void
    {
        $http = new MockHttpClient([new MockResponse('{"message":"Unauthorized"}', ['http_code' => 401])], 'https://api.example.test');

        try {
            $this->createClient($http)->getJson('/v1/voucherlist');
            self::fail('Expected LexwareApiException');
        } catch (LexwareApiException $exception) {
            self::assertSame(401, $exception->statusCode);
            self::assertStringContainsString('LEXWARE_API_KEY', $exception->getMessage());
        }

        self::assertSame(1, $http->getRequestsCount());
    }

    public function testClientErrorCarriesLexwareMessageAndValidationDetails(): void
    {
        $body = '{"status":406,"error":"Not Acceptable","path":"/v1/voucherlist","traceId":"90d78d0777be",'
            . '"message":"Validation failed for request.","details":[{"violation":"NOTNULL","field":"voucherType","message":"darf nicht leer sein"}]}';
        $http = new MockHttpClient([new MockResponse($body, ['http_code' => 406])], 'https://api.example.test');

        try {
            $this->createClient($http)->getJson('/v1/voucherlist');
            self::fail('Expected LexwareApiException');
        } catch (LexwareApiException $exception) {
            self::assertSame(406, $exception->statusCode);
            self::assertStringContainsString('Validation failed for request.', $exception->getMessage());
            self::assertStringContainsString('voucherType: darf nicht leer sein', $exception->getMessage());
            self::assertStringContainsString('traceId 90d78d0777be', $exception->getMessage());
        }
    }

    public function testLegacyOrNonJsonErrorBodyFallsBackToHint(): void
    {
        $http = new MockHttpClient([new MockResponse('<html>Bad Request</html>', ['http_code' => 400])], 'https://api.example.test');

        $this->expectException(LexwareApiException::class);
        $this->expectExceptionMessage('Lexware Office API returned HTTP 400 for /v1/voucherlist: unexpected response.');

        $this->createClient($http)->getJson('/v1/voucherlist');
    }

    public function testTransportErrorsAreRetried(): void
    {
        $http = new MockHttpClient([
            new MockResponse('', ['error' => 'Connection reset']),
            new MockResponse('{"ok":true}'),
        ], 'https://api.example.test');

        self::assertSame(['ok' => true], $this->createClient($http)->getJson('/v1/voucherlist'));
    }

    public function testInvalidJsonIsReportedAsApiError(): void
    {
        $http = new MockHttpClient([new MockResponse('not json')], 'https://api.example.test');

        $this->expectException(LexwareApiException::class);

        $this->createClient($http)->getJson('/v1/voucherlist');
    }

    private function createClient(MockHttpClient $http): LexwareClient
    {
        return new LexwareClient($http, new RequestThrottle($this->clock), $this->clock);
    }

    private function elapsedSeconds(): float
    {
        $start = new \DateTimeImmutable('2026-10-10 12:00:00.000000');

        return (float) $this->clock->now()->format('U.u') - (float) $start->format('U.u');
    }
}
