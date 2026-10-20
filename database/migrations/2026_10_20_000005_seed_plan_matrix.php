<?php

use App\Support\Plans\PlanMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        PlanMatrix::sync();
    }

    public function down(): void
    {
        DB::table('church_subscriptions')->delete();
        DB::table('plan_features')->delete();
        DB::table('plan_limits')->delete();
        DB::table('plans')->delete();
    }
};
