<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('church_id');
            $table->unique(['church_id', 'id'], 'ministry_teams_church_id_uq');
            $table->unique(['church_id', 'name'], 'ministry_teams_church_name_uq');
        });

        Schema::create('service_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->index(['church_id', 'starts_at']);
            $table->unique(['church_id', 'id'], 'service_plans_church_id_uq');
        });

        DB::statement("ALTER TABLE service_plans ADD CONSTRAINT service_plans_status_chk CHECK (status IN ('draft', 'published', 'completed', 'cancelled'))");
        DB::statement('ALTER TABLE service_plans ADD CONSTRAINT service_plans_time_range_chk CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at >= starts_at)');

        Schema::create('service_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_plan_id');
            $table->unsignedBigInteger('ministry_team_id')->nullable();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('church_id');
            $table->unique(['church_id', 'id'], 'service_items_church_id_uq');
            $table->foreign(['church_id', 'service_plan_id'], 'service_item_plan_fk')->references(['church_id', 'id'])->on('service_plans')->cascadeOnDelete();
            $table->foreign(['church_id', 'ministry_team_id'], 'service_item_team_fk')->references(['church_id', 'id'])->on('ministry_teams');
        });

        DB::statement('ALTER TABLE service_items ADD CONSTRAINT service_items_position_nonnegative_chk CHECK (position >= 0)');

        Schema::create('volunteer_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_item_id');
            $table->unsignedBigInteger('person_id');
            $table->string('role')->nullable();
            $table->string('status')->default('invited');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'service_item_id', 'person_id'], 'volunteer_assignment_uq');
            $table->index('church_id');
            $table->foreign(['church_id', 'service_item_id'], 'volunteer_item_fk')->references(['church_id', 'id'])->on('service_items')->cascadeOnDelete();
            $table->foreign(['church_id', 'person_id'], 'volunteer_person_fk')->references(['church_id', 'id'])->on('people')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE volunteer_assignments ADD CONSTRAINT volunteer_assignments_status_chk CHECK (status IN ('invited', 'confirmed', 'declined', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_assignments');
        Schema::dropIfExists('service_items');
        Schema::dropIfExists('service_plans');
        Schema::dropIfExists('ministry_teams');
    }
};
