<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use App\Models\Payment;

final class InvoicePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function admin(Invoice $invoice): array
    {
        return [
            ...self::base($invoice),
            'church' => [
                'id' => $invoice->church_id,
                'name' => $invoice->church_name,
                'slug' => $invoice->church_slug,
                'exists' => $invoice->church_id !== null,
            ],
            'issuedBy' => $invoice->issuedBy?->name,
            'payments' => $invoice->payments
                ->sortByDesc('paid_at')
                ->map(fn (Payment $payment): array => self::payment($payment, true))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function church(Invoice $invoice): array
    {
        return [
            ...self::base($invoice),
            'payments' => $invoice->payments
                ->sortByDesc('paid_at')
                ->map(fn (Payment $payment): array => self::payment($payment, false))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function base(Invoice $invoice): array
    {
        return [
            'id' => $invoice->getKey(),
            'number' => $invoice->number,
            'status' => $invoice->status,
            'statusLabel' => InvoiceStatus::label($invoice->status),
            'currencyCode' => $invoice->currency_code,
            'amountMinor' => $invoice->amount_minor,
            'paidMinor' => $invoice->paidMinor(),
            'remainingMinor' => $invoice->remainingMinor(),
            'description' => $invoice->description,
            'notes' => $invoice->notes,
            'issuedAt' => $invoice->issued_at?->toIso8601String(),
            'dueAt' => $invoice->due_at?->toIso8601String(),
            'paidAt' => $invoice->paid_at?->toIso8601String(),
            'voidedAt' => $invoice->voided_at?->toIso8601String(),
            'voidReason' => $invoice->void_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payment(Payment $payment, bool $includeActor): array
    {
        return [
            'id' => $payment->getKey(),
            'amountMinor' => $payment->amount_minor,
            'currencyCode' => $payment->currency_code,
            'method' => $payment->method,
            'methodLabel' => PaymentMethod::label($payment->method),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'paidAt' => $payment->paid_at?->toIso8601String(),
            'recordedBy' => $includeActor ? $payment->recordedBy?->name : null,
        ];
    }
}
