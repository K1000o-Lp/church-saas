<?php

namespace Database\Seeders;

use App\Support\Plans\PlanMatrix;
use Illuminate\Database\Seeder;

class PlanMatrixSeeder extends Seeder
{
    public function run(): void
    {
        PlanMatrix::sync();
    }
}
