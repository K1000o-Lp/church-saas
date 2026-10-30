<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectPaymentReceiptRequest;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PaymentReceipt;
use App\Support\Audit\DomainAudit;
use App\Support\Events\EventStatus;
use App\Support\Events\PaymentStatus;
use App\Support\Events\RegistrationStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReceiptController extends Controller
{
    public function index(Request $request, Church $church, string $event): Response
    {
        $record = $this->findEvent($event);
        Gate::authorize('view', $record);

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['pending', 'verified', 'rejected'])],
        ]);

        $query = PaymentReceipt::query()
            ->with(['registration.person'])
            ->whereIn(
                'event_registration_id',
                $record->registrations()->select('id'),
            );

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('events/receipts', [
            'church' => $this->churchPayload($church),
            'event' => [
                'id' => $record->getKey(),
                'title' => $record->title,
                'slug' => $record->slug,
                'status' => $record->status,
                'statusLabel' => EventStatus::label($record->status),
                'isPaid' => $record->is_paid,
                'price' => $record->price,
                'currencyCode' => $record->currency_code,
            ],
            'receipts' => $query
                ->orderByDesc('submitted_at')
                ->get()
                ->map(fn (PaymentReceipt $receipt): array => $this->summary($receipt))
                ->values(),
            'filters' => ['status' => $filters['status'] ?? ''],
            'statuses' => [
                ['value' => 'pending', 'label' => 'Pendientes'],
                ['value' => 'verified', 'label' => 'Verificados'],
                ['value' => 'rejected', 'label' => 'Rechazados'],
            ],
            'canManage' => Gate::allows('update', $record),
        ]);
    }

    public function verify(Church $church, string $event, string $receipt): RedirectResponse
    {
        $record = $this->findEvent($event);
        Gate::authorize('update', $record);
        $entry = $this->findReceipt($record, $receipt);

        DB::transaction(function () use ($entry): void {
            /** @var PaymentReceipt $locked */
            $locked = PaymentReceipt::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden verificar comprobantes pendientes.',
                ]);
            }

            /** @var EventRegistration $registration */
            $registration = EventRegistration::query()
                ->whereKey($locked->event_registration_id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'status' => 'verified',
                'verified_by' => $this->currentUserId(),
                'verified_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $registration->forceFill([
                'payment_status' => PaymentStatus::Paid->value,
                'status' => RegistrationStatus::Confirmed->value,
                'ticket_code_hash' => $registration->public_token_hash,
                'issued_at' => now(),
            ])->save();
        });

        DomainAudit::record(DomainAudit::RECEIPT_VERIFIED, $entry, [
            'registrationId' => $entry->event_registration_id,
            'amount' => $entry->amount,
            'currencyCode' => $entry->currency_code,
        ]);

        return back()->with('success', 'Comprobante verificado y pago aprobado.');
    }

    public function reject(
        RejectPaymentReceiptRequest $request,
        Church $church,
        string $event,
        string $receipt,
    ): RedirectResponse {
        $record = $this->findEvent($event);
        Gate::authorize('update', $record);
        $entry = $this->findReceipt($record, $receipt);
        $data = $request->validated();

        DB::transaction(function () use ($entry, $data): void {
            /** @var PaymentReceipt $locked */
            $locked = PaymentReceipt::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden rechazar comprobantes pendientes.',
                ]);
            }

            /** @var EventRegistration $registration */
            $registration = EventRegistration::query()
                ->whereKey($locked->event_registration_id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'status' => 'rejected',
                'verified_by' => $this->currentUserId(),
                'verified_at' => now(),
                'rejection_reason' => $data['reason'] ?? null,
            ])->save();

            $registration->forceFill([
                'payment_status' => PaymentStatus::Rejected->value,
            ])->save();
        });

        DomainAudit::record(DomainAudit::RECEIPT_REJECTED, $entry, [
            'registrationId' => $entry->event_registration_id,
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Comprobante rechazado.');
    }

    public function download(Church $church, string $event, string $receipt): StreamedResponse
    {
        $record = $this->findEvent($event);
        Gate::authorize('view', $record);
        $entry = $this->findReceipt($record, $receipt);

        abort_unless(Storage::disk('local')->exists($entry->file_path), 404);

        return Storage::disk('local')->download($entry->file_path, $entry->original_name);
    }

    private function findEvent(string $event): Event
    {
        return Event::query()->findOrFail($event);
    }

    private function findReceipt(Event $event, string $receipt): PaymentReceipt
    {
        return PaymentReceipt::query()
            ->whereKey($receipt)
            ->whereIn('event_registration_id', $event->registrations()->select('id'))
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(PaymentReceipt $receipt): array
    {
        $person = $receipt->registration?->person;

        return [
            'id' => $receipt->getKey(),
            'registrationId' => $receipt->event_registration_id,
            'person' => $person === null ? null : [
                'id' => $person->getKey(),
                'fullName' => trim($person->first_name.' '.($person->last_name ?? '')),
                'email' => $person->email,
            ],
            'guestName' => $receipt->registration?->guest_name,
            'amount' => $receipt->amount,
            'currencyCode' => $receipt->currency_code,
            'referenceNumber' => $receipt->reference_number,
            'status' => $receipt->status,
            'submittedAt' => $receipt->submitted_at?->toIso8601String(),
            'verifiedAt' => $receipt->verified_at?->toIso8601String(),
            'rejectionReason' => $receipt->rejection_reason,
        ];
    }

    private function currentUserId(): ?int
    {
        $userId = app(CurrentChurch::class)->membership()?->user_id;

        return $userId === null ? null : (int) $userId;
    }

    /**
     * @return array<string, mixed>
     */
    private function churchPayload(Church $church): array
    {
        return [
            'name' => $church->name,
            'slug' => $church->slug,
        ];
    }
}
