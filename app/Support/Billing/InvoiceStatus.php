<?php

namespace App\Support\Billing;

final class InvoiceStatus
{
    public const ISSUED = 'issued';

    public const PAID = 'paid';

    public const VOID = 'void';

    public const OVERDUE = 'overdue';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::ISSUED, self::PAID, self::VOID, self::OVERDUE];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ISSUED => 'Emitida',
            self::PAID => 'Pagada',
            self::VOID => 'Anulada',
            self::OVERDUE => 'Vencida',
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::labels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? $status;
    }

    public static function canAcceptPayments(string $status): bool
    {
        return in_array($status, [self::ISSUED, self::OVERDUE], true);
    }

    /**
     * Deliberately small state machine: paid invoices cannot be voided and
     * void invoices are terminal. A refund workflow belongs to a later phase.
     */
    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return match ($from) {
            self::ISSUED => in_array($to, [self::PAID, self::OVERDUE, self::VOID], true),
            self::OVERDUE => in_array($to, [self::PAID, self::VOID], true),
            default => false,
        };
    }
}
