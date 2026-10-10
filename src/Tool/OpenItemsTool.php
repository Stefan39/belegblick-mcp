<?php

declare(strict_types=1);

namespace Jacomeit\Belegblick\Tool;

use Jacomeit\Belegblick\Exception\LexwareApiException;
use Jacomeit\Belegblick\Lexware\LexwareClient;
use Jacomeit\Belegblick\Output\SensitiveDataFilter;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Psr\Clock\ClockInterface;

/**
 * Lists unpaid sales invoices (open and overdue) from the Lexware Office voucher list.
 *
 * @phpstan-type OpenItem array{id: string, voucherNumber: string, contactName: string, voucherDate: ?string, dueDate: ?string, totalAmount: float, openAmount: float, currency: string, status: string, overdue: bool, daysOverdue: int}
 * @phpstan-type OpenItemsResult array{asOf: string, count: int, overdueCount: int, totalsByCurrency: array<string, array{open: float, overdue: float}>, complete: bool, note: string, items: list<OpenItem>}
 */
final readonly class OpenItemsTool
{
    public const string NAME = 'open_items';

    public const string DESCRIPTION = 'Lists unpaid sales invoices (open and overdue receivables) from Lexware Office, '
        . 'oldest due date first, with totals per currency. Amounts are gross; net amounts need the voucher detail.';

    private const int PAGE_SIZE = 250;

    private const string NOTE = 'Amounts are gross (incl. VAT) as delivered by the Lexware voucher list. '
        . 'Only sales invoices are included; down payment invoices and credit notes are not.';

    public function __construct(
        private LexwareClient $client,
        private SensitiveDataFilter $filter,
        private ClockInterface $clock,
        private int $maxPages = 10,
    ) {}

    /**
     * @param bool    $overdueOnly Only return invoices whose due date has passed
     * @param ?string $contact     Case-insensitive part of the customer name to filter by
     *
     * @return OpenItemsResult
     */
    public function __invoke(
        #[Schema(description: 'Only return invoices whose due date has passed.')]
        bool $overdueOnly = false,
        #[Schema(description: 'Case-insensitive part of the customer name to filter by.')]
        ?string $contact = null,
    ): array {
        try {
            [$vouchers, $complete] = $this->fetchOpenSalesInvoices();
        } catch (LexwareApiException $exception) {
            throw new ToolCallException($exception->getMessage(), 0, $exception);
        }

        $today = self::toDate($this->clock->now()->format('Y-m-d'));
        $items = array_map(fn(array $voucher): array => $this->toItem($voucher, $today), $vouchers);
        $items = array_values(array_filter(
            $items,
            static fn(array $item): bool => (!$overdueOnly || $item['overdue']) && self::matchesContact($item, $contact),
        ));
        usort($items, static fn(array $a, array $b): int => [$a['dueDate'] ?? '9999', $a['voucherNumber']] <=> [$b['dueDate'] ?? '9999', $b['voucherNumber']]);

        return [
            'asOf' => $today->format('Y-m-d'),
            'count' => \count($items),
            'overdueCount' => \count(array_filter($items, static fn(array $item): bool => $item['overdue'])),
            'totalsByCurrency' => self::totals($items),
            'complete' => $complete,
            'note' => self::NOTE,
            'items' => $items,
        ];
    }

    /**
     * @return array{list<array<mixed>>, bool}
     */
    private function fetchOpenSalesInvoices(): array
    {
        $vouchers = [];
        for ($page = 0; $page < $this->maxPages; ++$page) {
            $response = $this->filter->filter($this->client->getJson('/v1/voucherlist', [
                'voucherType' => 'salesinvoice',
                'voucherStatus' => 'open,overdue',
                'page' => $page,
                'size' => self::PAGE_SIZE,
            ]));

            $content = \is_array($response['content'] ?? null) ? $response['content'] : [];
            array_push($vouchers, ...array_values(array_filter($content, \is_array(...))));

            if (true === ($response['last'] ?? true)) {
                return [$vouchers, true];
            }
        }

        return [$vouchers, false];
    }

    /**
     * @param array<mixed> $voucher
     *
     * @return OpenItem
     */
    private function toItem(array $voucher, \DateTimeImmutable $today): array
    {
        $status = self::string($voucher, 'voucherStatus');
        $dueDate = self::date($voucher, 'dueDate');
        $daysOverdue = null === $dueDate ? 0 : max(0, (int) $dueDate->diff($today)->format('%r%a'));

        return [
            'id' => self::string($voucher, 'id'),
            'voucherNumber' => self::string($voucher, 'voucherNumber'),
            'contactName' => self::string($voucher, 'contactName'),
            'voucherDate' => self::date($voucher, 'voucherDate')?->format('Y-m-d'),
            'dueDate' => $dueDate?->format('Y-m-d'),
            'totalAmount' => self::amount($voucher, 'totalAmount'),
            'openAmount' => self::amount($voucher, 'openAmount'),
            'currency' => self::string($voucher, 'currency', 'EUR'),
            'status' => $status,
            'overdue' => 'overdue' === $status || $daysOverdue > 0,
            'daysOverdue' => $daysOverdue,
        ];
    }

    /**
     * @param OpenItem $item
     */
    private static function matchesContact(array $item, ?string $contact): bool
    {
        $contact = trim($contact ?? '');

        return '' === $contact || false !== mb_stripos($item['contactName'], $contact);
    }

    /**
     * Sums in cents to avoid float drift across many invoices.
     *
     * @param list<OpenItem> $items
     *
     * @return array<string, array{open: float, overdue: float}>
     */
    private static function totals(array $items): array
    {
        $cents = [];
        foreach ($items as $item) {
            $cents[$item['currency']] ??= ['open' => 0, 'overdue' => 0];
            $amount = (int) round($item['openAmount'] * 100);
            $cents[$item['currency']]['open'] += $amount;
            $cents[$item['currency']]['overdue'] += $item['overdue'] ? $amount : 0;
        }

        return array_map(static fn(array $sum): array => ['open' => $sum['open'] / 100.0, 'overdue' => $sum['overdue'] / 100.0], $cents);
    }

    /**
     * @param array<mixed> $voucher
     */
    private static function string(array $voucher, string $key, string $default = ''): string
    {
        return \is_string($voucher[$key] ?? null) ? $voucher[$key] : $default;
    }

    /**
     * @param array<mixed> $voucher
     */
    private static function amount(array $voucher, string $key): float
    {
        return \is_int($voucher[$key] ?? null) || \is_float($voucher[$key] ?? null) ? round((float) $voucher[$key], 2) : 0.0;
    }

    /**
     * Lexware sends local timestamps; only the calendar date is meaningful for invoices.
     *
     * @param array<mixed> $voucher
     */
    private static function date(array $voucher, string $key): ?\DateTimeImmutable
    {
        $value = self::string($voucher, $key);
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $match)) {
            return null;
        }

        return self::toDate($match[0]);
    }

    private static function toDate(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
    }
}
