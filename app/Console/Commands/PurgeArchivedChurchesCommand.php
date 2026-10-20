<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Audit\PlatformAudit;
use App\Support\Church\ChurchStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurgeArchivedChurchesCommand extends Command
{
    protected $signature = 'churches:purge
        {--date= : Fecha de corte (YYYY-MM-DD); purga iglesias archivadas hasta esa fecha inclusive}
        {--dry-run : Muestra lo que se purgaría sin borrar datos}';

    protected $description = 'Purga iglesias archivadas hasta una fecha, conservando auditoría e historial de facturación';

    public function handle(): int
    {
        $value = (string) $this->option('date');

        if ($value === '') {
            $this->error('Debes indicar --date=YYYY-MM-DD de forma explícita.');

            return self::FAILURE;
        }

        try {
            $cutoff = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            $this->error('La fecha debe tener el formato YYYY-MM-DD.');

            return self::FAILURE;
        }

        if ($cutoff->format('Y-m-d') !== $value) {
            $this->error('La fecha indicada no es válida.');

            return self::FAILURE;
        }

        $cutoff = $cutoff->endOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $churches = Church::query()
            ->where('status', ChurchStatus::ARCHIVED)
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $cutoff)
            ->orderBy('archived_at')
            ->get();

        if ($churches->isEmpty()) {
            $this->info('No hay iglesias archivadas hasta '.$cutoff->toDateString().'.');

            return self::SUCCESS;
        }

        $rows = $churches->map(fn (Church $church): array => [
            $church->name,
            $church->slug,
            $church->archived_at?->toDateTimeString() ?? '—',
            $church->memberships()->count(),
            $church->people()->count(),
            $church->events()->count(),
            $church->contributions()->count(),
            $church->invoices()->count(),
        ])->all();

        $this->table(
            ['Iglesia', 'Slug', 'Archivada', 'Membresías', 'Personas', 'Eventos', 'Contribuciones', 'Facturas'],
            $rows,
        );

        if ($dryRun) {
            $this->warn('Dry run: no se borró ningún dato. Se purgarían '.$churches->count().' iglesia(s).');

            return self::SUCCESS;
        }

        $purged = 0;

        foreach ($churches as $church) {
            DB::transaction(function () use ($church, $cutoff): void {
                $counts = [
                    'memberships' => $church->memberships()->count(),
                    'people' => $church->people()->count(),
                    'events' => $church->events()->count(),
                    'contributions' => $church->contributions()->count(),
                    'workflows' => $church->workflows()->count(),
                    'invoices' => $church->invoices()->count(),
                    'payments' => $church->payments()->count(),
                ];

                PlatformAudit::recordSystem(PlatformAudit::CHURCH_PURGED, $church, [
                    'cutoff' => $cutoff->toDateString(),
                    'archivedAt' => $church->archived_at?->toIso8601String(),
                    'counts' => $counts,
                    'preserved' => [
                        'invoices' => $counts['invoices'],
                        'payments' => $counts['payments'],
                        'auditEntries' => $church->auditEntries()->count(),
                    ],
                ]);

                // Tenant data cascades from the church row. Billing FKs use
                // nullOnDelete so invoices and payments keep their history.
                $church->delete();
            });

            $purged++;
        }

        $this->info('Iglesias purgadas: '.$purged.'. Auditoría e historial de facturación conservados.');
        $this->line('Facturas conservadas: '.Invoice::query()->whereNull('church_id')->count().'.');
        $this->line('Pagos conservados: '.Payment::query()->whereNull('church_id')->count().'.');

        return self::SUCCESS;
    }
}
