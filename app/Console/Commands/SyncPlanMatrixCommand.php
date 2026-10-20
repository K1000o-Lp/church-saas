<?php

namespace App\Console\Commands;

use App\Support\Plans\PlanMatrix;
use Illuminate\Console\Command;

class SyncPlanMatrixCommand extends Command
{
    protected $signature = 'plans:sync';

    protected $description = 'Sincroniza la matriz de planes y asigna Comunidad a iglesias sin suscripción';

    public function handle(): int
    {
        PlanMatrix::sync();

        $this->info('Matriz de planes sincronizada.');

        return self::SUCCESS;
    }
}
