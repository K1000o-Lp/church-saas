<?php

namespace App\Console\Commands\Concerns;

use App\Models\Church;
use App\Support\Church\ChurchStatus;
use App\Support\Tenancy\CurrentChurch;
use Closure;
use RuntimeException;

trait InteractsWithTenant
{
    protected function tenantFromOption(CurrentChurch $currentChurch, string $option = 'church'): Church
    {
        $value = $this->option($option);

        if ($value === null || trim((string) $value) === '') {
            throw new RuntimeException("Debes indicar --{$option}=<slug|id> de forma explícita.");
        }

        $value = trim((string) $value);

        $church = Church::query()
            ->where('slug', $value)
            ->when(ctype_digit($value), fn ($query) => $query->orWhere('id', (int) $value))
            ->first();

        if (! $church instanceof Church) {
            throw new RuntimeException('La iglesia indicada no existe.');
        }

        if (ChurchStatus::blocksAccess($church->status)) {
            throw new RuntimeException('La iglesia indicada no está activa.');
        }

        return $church;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(Church): TReturn  $callback
     * @return TReturn
     */
    protected function runInTenant(CurrentChurch $currentChurch, Church $church, Closure $callback): mixed
    {
        return $currentChurch->run($church, fn (): mixed => $callback($church));
    }
}
