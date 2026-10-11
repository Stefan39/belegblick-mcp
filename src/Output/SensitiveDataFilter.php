<?php

declare(strict_types=1);

namespace Jacomeit\LexwarePhpMcp\Output;

/**
 * Removes bank details from any API payload before it reaches the model.
 */
final readonly class SensitiveDataFilter
{
    private const array SENSITIVE_KEYS = [
        'iban',
        'bic',
        'bankaccount',
        'bankaccounts',
        'bankcode',
        'bankname',
        'accountholder',
        'accountnumber',
        'sepamandate',
        'creditorid',
    ];

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public function filter(array $data): array
    {
        $filtered = [];
        foreach ($data as $key => $value) {
            if (\is_string($key) && \in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $filtered[$key] = \is_array($value) ? $this->filter($value) : $value;
        }

        return $filtered;
    }
}
