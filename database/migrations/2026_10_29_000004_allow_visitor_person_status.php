<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_status_chk');
        DB::statement("ALTER TABLE people ADD CONSTRAINT people_status_chk CHECK (status IN ('active', 'inactive', 'archived', 'visitor'))");
    }

    public function down(): void
    {
        DB::table('people')->where('status', 'visitor')->update(['status' => 'active']);

        DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_status_chk');
        DB::statement("ALTER TABLE people ADD CONSTRAINT people_status_chk CHECK (status IN ('active', 'inactive', 'archived'))");
    }
};
