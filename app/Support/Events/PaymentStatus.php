<?php

namespace App\Support\Events;

enum PaymentStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public static function label(string $status): string
    {
        return match ($status) {
            self::NotRequired->value => 'No aplica',
            self::Pending->value => 'Pendiente',
            self::UnderReview->value => 'En revisión',
            self::Paid->value => 'Pagado',
            self::Rejected->value => 'Rechazado',
            default => $status,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
