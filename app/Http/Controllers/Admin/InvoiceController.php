<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdateInvoiceStatusRequest;
use App\Models\Church;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PlatformAdmin;
use App\Support\Audit\PlatformAudit;
use App\Support\Billing\InvoicePresenter;
use App\Support\Billing\InvoiceStatus;
use App\Support\Billing\Money;
use App\Support\Billing\PaymentMethod;
use App\Support\Church\ChurchStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Invoice::markOverdue();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', Rule::in(InvoiceStatus::all())],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;

        $query = Invoice::query()->with(['issuedBy', 'payments.recordedBy']);

        if ($search !== '') {
            $like = '%'.Str::lower($search).'%';

            $query->where(function ($query) use ($like): void {
                $query->whereRaw('lower(number) like ?', [$like])
                    ->orWhereRaw('lower(church_name) like ?', [$like])
                    ->orWhereRaw('lower(church_slug) like ?', [$like]);
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $paginator = $query->orderByDesc('issued_at')->paginate(20)->withQueryString();

        return Inertia::render('admin/invoices/index', [
            'invoices' => $paginator->getCollection()
                ->map(fn (Invoice $invoice): array => InvoicePresenter::admin($invoice))
                ->values(),
            'filters' => [
                'search' => $search,
                'status' => $status ?? '',
            ],
            'statuses' => InvoiceStatus::options(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'previousPageUrl' => $paginator->previousPageUrl(),
                'nextPageUrl' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function forChurch(Church $church): Response
    {
        Invoice::markOverdue($church);

        $invoices = $church->invoices()
            ->with(['issuedBy', 'payments.recordedBy'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Invoice $invoice): array => InvoicePresenter::admin($invoice))
            ->values();

        return Inertia::render('admin/churches/invoices', [
            'church' => [
                'id' => $church->getKey(),
                'name' => $church->name,
                'slug' => $church->slug,
                'status' => $church->status,
                'statusLabel' => ChurchStatus::label($church->status),
                'currencyCode' => $church->currency_code,
            ],
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::options(),
            'paymentMethods' => PaymentMethod::options(),
            'canEdit' => ChurchStatus::canEdit($church->status),
        ]);
    }

    public function store(StoreInvoiceRequest $request, Church $church): RedirectResponse
    {
        /** @var PlatformAdmin $admin */
        $admin = $request->attributes->get('platformAdmin');

        abort_unless($admin instanceof PlatformAdmin, 403);
        abort_unless(ChurchStatus::canEdit($church->status), 403);

        /** @var array{amount: string, description?: ?string, notes?: ?string, due_at?: ?string} $data */
        $data = $request->validated();
        $amountMinor = Money::toMinor($data['amount']);
        $issuedAt = now();
        $dueAt = isset($data['due_at']) && $data['due_at'] !== null
            ? Carbon::parse($data['due_at'])
            : null;

        $invoice = DB::transaction(function () use ($church, $data, $amountMinor, $issuedAt, $dueAt, $admin): Invoice {
            $invoice = Invoice::create([
                'church_id' => $church->getKey(),
                'church_name' => $church->name,
                'church_slug' => $church->slug,
                'number' => 'PENDING-'.Str::ulid(),
                'status' => InvoiceStatus::ISSUED,
                'currency_code' => Money::CURRENCY,
                'amount_minor' => $amountMinor,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
                'issued_by_platform_admin_id' => $admin->getKey(),
            ]);

            $invoice->forceFill([
                'number' => sprintf(
                    'INV-%s-%06d',
                    $issuedAt->format('Y'),
                    $invoice->getKey(),
                ),
            ])->save();

            PlatformAudit::record($admin, PlatformAudit::INVOICE_ISSUED, $church, [
                'invoiceId' => $invoice->getKey(),
                'number' => $invoice->number,
                'amountMinor' => $invoice->amount_minor,
                'currencyCode' => $invoice->currency_code,
                'dueAt' => $invoice->due_at?->toIso8601String(),
            ]);

            return $invoice;
        });

        return redirect()
            ->route('admin.churches.invoices', $church)
            ->with('success', 'Factura '.$invoice->number.' emitida.');
    }

    public function updateStatus(UpdateInvoiceStatusRequest $request, Invoice $invoice): RedirectResponse
    {
        /** @var PlatformAdmin $admin */
        $admin = $request->attributes->get('platformAdmin');

        abort_unless($admin instanceof PlatformAdmin, 403);

        $church = $invoice->church;

        abort_unless($church !== null, 403);
        abort_unless(ChurchStatus::canEdit($church->status), 403);

        /** @var array{status: string, reason?: ?string} $data */
        $data = $request->validated();
        $target = $data['status'];

        DB::transaction(function () use ($invoice, $target, $data, $admin, $church): void {
            /** @var Invoice $locked */
            $locked = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = $locked->status;

            if (! InvoiceStatus::canTransition($from, $target)) {
                throw ValidationException::withMessages([
                    'status' => 'No se puede cambiar una factura de '.InvoiceStatus::label($from).' a '.InvoiceStatus::label($target).'.',
                ]);
            }

            if ($target === InvoiceStatus::PAID && ! $locked->isFullyPaid()) {
                throw ValidationException::withMessages([
                    'status' => 'Registra los pagos restantes antes de marcar la factura como pagada.',
                ]);
            }

            if ($target === InvoiceStatus::OVERDUE && $locked->due_at === null) {
                throw ValidationException::withMessages([
                    'status' => 'La factura necesita una fecha de vencimiento para marcarla como vencida.',
                ]);
            }

            if ($target === InvoiceStatus::OVERDUE && $locked->due_at->isFuture()) {
                throw ValidationException::withMessages([
                    'status' => 'La fecha de vencimiento todavía no ha pasado.',
                ]);
            }

            $locked->status = $target;

            if ($target === InvoiceStatus::PAID) {
                $locked->paid_at = $locked->payments()->max('paid_at') ?? now();
            }

            if ($target === InvoiceStatus::VOID) {
                $locked->voided_at = now();
                $locked->void_reason = $data['reason'] ?? null;
            }

            $locked->save();

            PlatformAudit::record($admin, PlatformAudit::INVOICE_STATUS_CHANGED, $church, [
                'invoiceId' => $locked->getKey(),
                'number' => $locked->number,
                'from' => $from,
                'to' => $target,
                'reason' => $data['reason'] ?? null,
                'amountMinor' => $locked->amount_minor,
            ]);
        });

        return redirect()
            ->route('admin.churches.invoices', $church)
            ->with('success', 'Estado de la factura actualizado.');
    }

    public function storePayment(StorePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        /** @var PlatformAdmin $admin */
        $admin = $request->attributes->get('platformAdmin');

        abort_unless($admin instanceof PlatformAdmin, 403);

        $church = $invoice->church;

        abort_unless($church !== null, 403);
        abort_unless(ChurchStatus::canEdit($church->status), 403);

        /** @var array{amount: string, method: string, reference?: ?string, notes?: ?string, paid_at: string} $data */
        $data = $request->validated();
        $amountMinor = Money::toMinor($data['amount']);
        $paidAt = Carbon::parse($data['paid_at']);

        DB::transaction(function () use ($invoice, $data, $amountMinor, $paidAt, $admin, $church): void {
            /** @var Invoice $locked */
            $locked = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! InvoiceStatus::canAcceptPayments($locked->status)) {
                throw ValidationException::withMessages([
                    'amount' => 'La factura no admite pagos en estado '.InvoiceStatus::label($locked->status).'.',
                ]);
            }

            if ($paidAt->toDateString() < $locked->issued_at->toDateString()) {
                throw ValidationException::withMessages([
                    'paid_at' => 'La fecha del pago no puede ser anterior a la emisión de la factura.',
                ]);
            }

            $remaining = $locked->remainingMinor();

            if ($amountMinor > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'El pago excede el saldo pendiente de la factura.',
                ]);
            }

            $payment = Payment::create([
                'invoice_id' => $locked->getKey(),
                'church_id' => $locked->church_id,
                'amount_minor' => $amountMinor,
                'currency_code' => Money::CURRENCY,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $paidAt,
                'recorded_by_platform_admin_id' => $admin->getKey(),
            ]);

            $paidMinor = $locked->paidMinor();

            if ($paidMinor >= $locked->amount_minor) {
                $locked->status = InvoiceStatus::PAID;
                $locked->paid_at = $paidAt;
            } elseif ($locked->status === InvoiceStatus::ISSUED && $locked->due_at?->isPast()) {
                $locked->status = InvoiceStatus::OVERDUE;
            }

            $locked->save();

            PlatformAudit::record($admin, PlatformAudit::PAYMENT_RECORDED, $church, [
                'invoiceId' => $locked->getKey(),
                'number' => $locked->number,
                'paymentId' => $payment->getKey(),
                'amountMinor' => $payment->amount_minor,
                'currencyCode' => $payment->currency_code,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'paidAt' => $payment->paid_at?->toIso8601String(),
                'invoiceStatus' => $locked->status,
            ]);
        });

        return redirect()
            ->route('admin.churches.invoices', $church)
            ->with('success', 'Pago registrado.');
    }
}
