<?php

declare(strict_types=1);

namespace Jacomeit\Belegblick\Lexware;

use Jacomeit\Belegblick\Exception\LexwareApiException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Read-only GET client for the Lexware Office public API with throttling and retry.
 *
 * Retries 429, 5xx and transport failures with exponential backoff (1 s, 2 s, ...),
 * honouring a numeric Retry-After header when the API sends one.
 */
final readonly class LexwareClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private RequestThrottle $throttle,
        private ClockInterface $clock,
        private int $maxAttempts = 3,
    ) {}

    /**
     * @param array<string, scalar> $query
     *
     * @return array<mixed>
     */
    public function getJson(string $path, array $query = []): array
    {
        $attempt = 1;
        while (true) {
            $this->throttle->wait();
            $outcome = $this->attempt($path, $query);
            if (\is_array($outcome)) {
                return $outcome;
            }

            if (!$this->isRetryable($outcome) || $attempt >= $this->maxAttempts) {
                throw $outcome->exception;
            }

            $this->clock->sleep($outcome->retryAfter ?? 2 ** ($attempt - 1));
            ++$attempt;
        }
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<mixed>|FailedAttempt
     */
    private function attempt(string $path, array $query): array|FailedAttempt
    {
        try {
            $response = $this->httpClient->request('GET', $path, ['query' => $query]);
            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                return new FailedAttempt(
                    LexwareApiException::fromStatus($statusCode, $path, LexwareErrorBody::describe($response->getContent(false))),
                    $statusCode,
                    self::parseRetryAfter($response->getHeaders(false)['retry-after'][0] ?? null),
                );
            }

            return $response->toArray();
        } catch (DecodingExceptionInterface $exception) {
            throw LexwareApiException::invalidResponse($path, $exception);
        } catch (TransportExceptionInterface $exception) {
            return new FailedAttempt(LexwareApiException::transportFailure($path, $exception), null, null);
        }
    }

    private function isRetryable(FailedAttempt $attempt): bool
    {
        return null === $attempt->statusCode || 429 === $attempt->statusCode || $attempt->statusCode >= 500;
    }

    private static function parseRetryAfter(?string $header): ?int
    {
        if (null === $header || !ctype_digit(trim($header))) {
            return null;
        }

        return min((int) trim($header), 30);
    }
}
