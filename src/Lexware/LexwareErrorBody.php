<?php

declare(strict_types=1);

namespace Jacomeit\Belegblick\Lexware;

/**
 * Turns Lexware's "regular error response" body into one readable line.
 *
 * Format: {"status":406,"error":"Not Acceptable","message":"...","traceId":"...",
 *          "details":[{"violation":"NOTNULL","field":"...","message":"..."}]}
 * Legacy or non-JSON bodies yield null so the caller falls back to a generic hint.
 *
 * @internal
 */
final class LexwareErrorBody
{
    private const int MAX_LENGTH = 500;

    public static function describe(string $body): ?string
    {
        $error = json_decode($body, true);
        if (!\is_array($error) || !\is_string($error['message'] ?? null) || '' === trim($error['message'])) {
            return null;
        }

        $parts = [trim($error['message'])];
        foreach (\is_array($error['details'] ?? null) ? $error['details'] : [] as $detail) {
            if (\is_array($detail) && \is_string($detail['message'] ?? null)) {
                $field = \is_string($detail['field'] ?? null) ? $detail['field'] . ': ' : '';
                $parts[] = $field . $detail['message'];
            }
        }

        $description = implode('; ', $parts);
        if (\is_string($error['traceId'] ?? null)) {
            $description .= ' (traceId ' . $error['traceId'] . ')';
        }

        return mb_substr($description, 0, self::MAX_LENGTH);
    }
}
