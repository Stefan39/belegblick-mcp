<?php

declare(strict_types=1);

namespace Jacomeit\LexwarePhpMcp\Tests\Output;

use Jacomeit\LexwarePhpMcp\Output\SensitiveDataFilter;
use PHPUnit\Framework\TestCase;

final class SensitiveDataFilterTest extends TestCase
{
    public function testRemovesBankDataAtAnyDepthCaseInsensitively(): void
    {
        $input = [
            'voucherNumber' => 'RE-1',
            'IBAN' => 'DE02120300000000202051',
            'contact' => [
                'name' => 'ACME GmbH',
                'bic' => 'BYLADEM1001',
                'bankAccounts' => [['iban' => 'DE02120300000000202051']],
            ],
            'items' => [
                ['accountHolder' => 'ACME', 'amount' => 10.0],
            ],
        ];

        self::assertSame([
            'voucherNumber' => 'RE-1',
            'contact' => ['name' => 'ACME GmbH'],
            'items' => [['amount' => 10.0]],
        ], new SensitiveDataFilter()->filter($input));
    }

    public function testLeavesUnrelatedDataUntouched(): void
    {
        $input = ['openAmount' => 119.0, 'list' => [1, 2, 3]];

        self::assertSame($input, new SensitiveDataFilter()->filter($input));
    }
}
