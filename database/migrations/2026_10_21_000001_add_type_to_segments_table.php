<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('segments', function (Blueprint $table): void {
            $table->string('type')->default('static');
        });

        DB::statement("UPDATE segments SET type = CASE WHEN rules IS NULL OR rules = '{}'::jsonb THEN 'static' ELSE 'dynamic' END");
        DB::statement("UPDATE segments SET rules = NULL WHERE type = 'static' AND rules = '{}'::jsonb");

        DB::statement("ALTER TABLE segments ADD CONSTRAINT segments_type_chk CHECK (type IN ('dynamic', 'static'))");
        DB::statement("ALTER TABLE segments ADD CONSTRAINT segments_type_rules_chk CHECK ((type = 'dynamic' AND rules IS NOT NULL) OR (type = 'static' AND rules IS NULL))");

        Schema::table('segments', function (Blueprint $table): void {
            $table->unique(['church_id', 'name'], 'segments_church_name_uq');
        });
    }

    public function down(): void
    {
        Schema::table('segments', function (Blueprint $table): void {
            $table->dropUnique('segments_church_name_uq');
        });

        DB::statement('ALTER TABLE segments DROP CONSTRAINT IF EXISTS segments_type_rules_chk');
        DB::statement('ALTER TABLE segments DROP CONSTRAINT IF EXISTS segments_type_chk');

        Schema::table('segments', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
