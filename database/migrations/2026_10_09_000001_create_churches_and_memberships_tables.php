<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Requires PostgreSQL for churches.settings JSONB.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->char('country_code', 2)->nullable();
            $table->string('timezone')->nullable();
            $table->string('currency_code', 12)->nullable();
            $table->string('locale')->default('es');
            $table->jsonb('settings')->default('{}');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE churches ADD CONSTRAINT churches_status_chk CHECK (status IN ('active', 'inactive', 'suspended'))");
        DB::statement("ALTER TABLE churches ADD CONSTRAINT churches_settings_object_chk CHECK (jsonb_typeof(settings) = 'object')");

        Schema::create('church_currencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 12);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['church_id', 'currency_code'], 'church_currency_code_uq');
            $table->index('church_id');
        });

        DB::statement('CREATE UNIQUE INDEX church_currencies_one_default_uq ON church_currencies (church_id) WHERE is_default');
        DB::statement("ALTER TABLE church_currencies ADD CONSTRAINT church_currencies_code_not_blank_chk CHECK (btrim(currency_code) <> '')");

        Schema::create('church_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['church_id', 'user_id']);
            $table->index('church_id');
            $table->index(['user_id', 'status']);
        });

        DB::statement("ALTER TABLE church_memberships ADD CONSTRAINT church_memberships_status_chk CHECK (status IN ('active', 'invited', 'suspended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('church_memberships');
        Schema::dropIfExists('church_currencies');
        Schema::dropIfExists('churches');
    }
};
