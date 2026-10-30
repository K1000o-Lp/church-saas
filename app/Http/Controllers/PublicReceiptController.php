<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicReceiptRequest;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PaymentReceipt;
use App\Support\Audit\DomainAudit;
use App\Support\Events\PaymentStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicReceiptController extends Controller
{
    public function show(string $token): Response
    {
        [$church, $registration] = $this->resolve($token);

        return Inertia::render('public/receipt', [
            'token' => $token,
            'church' => $this->churchPayload($church),
            'event' => [
                'id' => $registration->event->getKey(),
                'title' => $registration->event->title,
                'slug' => $registration->event->slug,
                'price' => $registration->event->price,
                'currencyCode' => $registration->event->currency_code,
            ],
            'registration' => [
                'status' => $registration->status,
                'paymentStatus' => $registration->payment_status,
                'paymentStatusLabel' => PaymentStatus::label($registration->payment_status),
            ],
            'receipts' => $registration->receipts
                ->sortByDesc('submitted_at')
                ->map(fn (PaymentReceipt $receipt): array => [
                    'id' => $receipt->getKey(),
                    'amount' => $receipt->amount,
                    'currencyCode' => $receipt->currency_code,
                    'referenceNumber' => $receipt->reference_number,
                    'status' => $receipt->status,
                    'submittedAt' => $receipt->submitted_at?->toIso8601String(),
                    'rejectionReason' => $receipt->rejection_reason,
                ])
                ->values(),
            'ticketUrl' => $registration->issued_at === null
                ? null
                : route('public.ticket.show', ['token' => $token]),
            'canUpload' => in_array($registration->payment_status, [
                PaymentStatus::Pending->value,
                PaymentStatus::Rejected->value,
            ], true),
        ]);
    }

    public function store(PublicReceiptRequest $request, string $token): RedirectResponse
    {
        [$church, $registration] = $this->resolve($token);

        if (! in_array($registration->payment_status, [
            PaymentStatus::Pending->value,
            PaymentStatus::Rejected->value,
        ], true)) {
            return back()->with('error', 'El pago de esta inscripción ya fue procesado.');
        }

        $hasPending = $registration->receipts()
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return back()->with('error', 'Ya hay un comprobante en revisión.');
        }

        $data = $request->validated();
        $file = $request->file('file');
        $path = $file->store(
            "receipts/{$church->getKey()}/{$registration->getKey()}",
            'local',
        );

        $receipt = app(CurrentChurch::class)->run($church, function () use ($registration, $data, $file, $path): PaymentReceipt {
            return DB::transaction(function () use ($registration, $data, $file, $path): PaymentReceipt {
                $receipt = PaymentReceipt::create([
                    'event_registration_id' => $registration->getKey(),
                    'amount' => $data['amount'],
                    'currency_code' => $registration->event->currency_code,
                    'reference_number' => $data['reference_number'] ?? null,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size' => $file->getSize(),
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]);

                $registration->forceFill([
                    'payment_status' => PaymentStatus::UnderReview->value,
                ])->save();

                return $receipt;
            });
        });

        DomainAudit::recordForChurch($church, DomainAudit::RECEIPT_SUBMITTED, $receipt, [
            'registrationId' => $registration->getKey(),
            'amount' => $receipt->amount,
            'currencyCode' => $receipt->currency_code,
        ]);

        return back()->with('success', 'Comprobante enviado. La iglesia lo revisará pronto.');
    }

    /**
     * @return array{0: Church, 1: EventRegistration}
     */
    private function resolve(string $token): array
    {
        $base = EventRegistration::withoutGlobalScope('church')
            ->where('public_token_hash', hash('sha256', $token))
            ->firstOrFail();

        $church = Church::query()->findOrFail($base->church_id);

        $registration = app(CurrentChurch::class)->run($church, fn (): EventRegistration => EventRegistration::query()
            ->with(['event', 'person', 'receipts'])
            ->findOrFail($base->getKey()));

        abort_unless($registration->event instanceof Event && $registration->event->is_paid, 404);

        return [$church, $registration];
    }

    /**
     * @return array<string, mixed>
     */
    private function churchPayload(Church $church): array
    {
        $branding = $church->settings['branding'] ?? [];
        $logoPath = $branding['logo_path'] ?? null;

        return [
            'name' => $church->name,
            'slug' => $church->slug,
            'tagline' => $branding['tagline'] ?? null,
            'logoUrl' => $logoPath === null ? null : Storage::disk('public')->url($logoPath),
        ];
    }
}
