<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateChurchRequest;
use App\Http\Requests\Admin\UpdateChurchStatusRequest;
use App\Models\Church;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditEntry;
use App\Support\Audit\PlatformAudit;
use App\Support\Church\ChurchCurrencies;
use App\Support\Church\ChurchMetrics;
use App\Support\Church\ChurchStatus;
use App\Support\Church\OnboardingOptions;
use App\Support\Plans\PlanEnforcement;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChurchController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', Rule::in(ChurchStatus::all())],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;

        $query = Church::query();

        if ($search !== '') {
            $like = '%'.Str::lower($search).'%';

            $query->where(function ($query) use ($like): void {
                $query->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(slug) like ?', [$like]);
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', ChurchStatus::ARCHIVED);
        }

        $paginator = $query->orderBy('name')->paginate(20)->withQueryString();
        $metrics = ChurchMetrics::forChurches($paginator->getCollection());

        return Inertia::render('admin/churches/index', [
            'churches' => $paginator->getCollection()
                ->map(fn (Church $church): array => $this->summary($church, $metrics[$church->getKey()] ?? $this->emptyMetrics()))
                ->values(),
            'filters' => [
                'search' => $search,
                'status' => $status ?? '',
            ],
            'statuses' => ChurchStatus::options(),
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

    public function show(Church $church): Response
    {
        $church->load('statusChangedBy');
        $metrics = ChurchMetrics::forChurch($church);

        $auditEntries = PlatformAuditEntry::query()
            ->with('platformAdmin')
            ->where('church_id', $church->getKey())
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (PlatformAuditEntry $entry): array => [
                'id' => $entry->getKey(),
                'action' => $entry->action,
                'actor' => $entry->platformAdmin?->name,
                'actorEmail' => $entry->platformAdmin?->email,
                'createdAt' => $entry->created_at?->toIso8601String(),
                'metadata' => $entry->metadata ?? [],
            ])
            ->values();

        return Inertia::render('admin/churches/show', [
            'church' => [
                ...$this->summary($church, $metrics),
                'locale' => $church->locale,
                'statusReason' => $church->status_reason,
                'statusChangedAt' => $church->status_changed_at?->toIso8601String(),
                'statusChangedBy' => $church->statusChangedBy?->name,
                'archivedAt' => $church->archived_at?->toIso8601String(),
                'createdAt' => $church->created_at?->toIso8601String(),
                'updatedAt' => $church->updated_at?->toIso8601String(),
            ],
            'currencies' => ChurchCurrencies::forChurch($church)
                ->map(fn ($currency): array => [
                    'code' => $currency->currency_code,
                    'isDefault' => $currency->is_default,
                ])
                ->values(),
            'auditEntries' => $auditEntries,
            'canEdit' => ChurchStatus::canEdit($church->status),
            'options' => [
                'countries' => OnboardingOptions::countries(),
                'currencies' => OnboardingOptions::currencies(),
                'locales' => OnboardingOptions::locales(),
                'statuses' => ChurchStatus::options(),
            ],
        ]);
    }

    public function update(UpdateChurchRequest $request, Church $church, CurrentChurch $currentChurch): RedirectResponse
    {
        /** @var PlatformAdmin $admin */
        $admin = $request->attributes->get('platformAdmin');

        abort_unless($admin instanceof PlatformAdmin, 403);

        if (! ChurchStatus::canEdit($church->status)) {
            throw ValidationException::withMessages([
                'name' => 'Restaura la iglesia antes de editar sus datos.',
            ]);
        }

        /** @var array{name: string, country_code: string, timezone: string, locale: string, currency_code: string, additional_currencies?: array<int, string>} $data */
        $data = $request->validated();

        DB::transaction(function () use ($church, $data, $currentChurch, $admin): void {
            $church->fill([
                'name' => $data['name'],
                'country_code' => $data['country_code'],
                'timezone' => $data['timezone'],
                'locale' => $data['locale'],
                'currency_code' => $data['currency_code'],
            ]);

            $changes = [];

            foreach (['name', 'country_code', 'timezone', 'locale', 'currency_code'] as $field) {
                if ($church->isDirty($field)) {
                    $changes[$field] = [
                        'from' => $church->getOriginal($field),
                        'to' => $church->getAttribute($field),
                    ];
                }
            }

            $church->save();

            // Platform administration is trusted and may exceed plan limits while fixing tenant data.
            $currencyChanges = PlanEnforcement::without(fn (): array => ChurchCurrencies::sync(
                $church,
                $data['currency_code'],
                $data['additional_currencies'] ?? [],
                $currentChurch,
            ));

            if ($currencyChanges['changed']) {
                $changes['currencies'] = [
                    'from' => $currencyChanges['before'],
                    'to' => $currencyChanges['after'],
                ];
            }

            if ($changes !== []) {
                PlatformAudit::record($admin, PlatformAudit::CHURCH_UPDATED, $church, [
                    'changes' => $changes,
                ]);
            }
        });

        return redirect()
            ->route('admin.churches.show', $church)
            ->with('success', 'Datos de la iglesia actualizados.');
    }

    public function updateStatus(UpdateChurchStatusRequest $request, Church $church): RedirectResponse
    {
        /** @var PlatformAdmin $admin */
        $admin = $request->attributes->get('platformAdmin');

        abort_unless($admin instanceof PlatformAdmin, 403);

        /** @var array{status: string, reason?: ?string} $data */
        $data = $request->validated();

        $previousStatus = $church->status;
        $status = $data['status'];
        $reason = $data['reason'] ?? null;

        DB::transaction(function () use ($church, $status, $reason, $previousStatus, $admin): void {
            $church->forceFill([
                'status' => $status,
                'status_reason' => $reason,
                'status_changed_at' => now(),
                'status_changed_by_platform_admin_id' => $admin->getKey(),
                'archived_at' => $status === ChurchStatus::ARCHIVED
                    ? ($church->archived_at ?? now())
                    : null,
            ])->save();

            PlatformAudit::record($admin, PlatformAudit::CHURCH_STATUS_CHANGED, $church, [
                'from' => $previousStatus,
                'to' => $status,
                'reason' => $reason,
            ]);
        });

        return redirect()
            ->route('admin.churches.show', $church)
            ->with('success', 'Estado de la iglesia actualizado a '.ChurchStatus::label($status).'.');
    }

    /**
     * @param  array{activeMemberships: int, people: int, events: int, servicePlans: int, workflows: int}  $metrics
     * @return array<string, mixed>
     */
    private function summary(Church $church, array $metrics): array
    {
        return [
            'id' => $church->getKey(),
            'name' => $church->name,
            'slug' => $church->slug,
            'status' => $church->status,
            'statusLabel' => ChurchStatus::label($church->status),
            'countryCode' => $church->country_code,
            'currencyCode' => $church->currency_code,
            'timezone' => $church->timezone,
            'createdAt' => $church->created_at?->toIso8601String(),
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{activeMemberships: int, people: int, events: int, servicePlans: int, workflows: int}
     */
    private function emptyMetrics(): array
    {
        return [
            'activeMemberships' => 0,
            'people' => 0,
            'events' => 0,
            'servicePlans' => 0,
            'workflows' => 0,
        ];
    }
}
