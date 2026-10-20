<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Requires PostgreSQL for JSONB columns and the GIN index; SQLite is not a supported migration target.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->jsonb('custom_fields')->default('{}');
            $table->timestamps();
            $table->index('church_id');
            $table->index(['church_id', 'email']);
            $table->unique(['church_id', 'id'], 'people_church_id_uq');
        });

        // PostgreSQL-only: SQLite does not support this JSONB GIN index and must not be used to validate it.
        DB::statement('CREATE INDEX people_custom_fields_gin ON people USING GIN (custom_fields)');
        DB::statement("ALTER TABLE people ADD CONSTRAINT people_status_chk CHECK (status IN ('active', 'inactive', 'archived'))");
        DB::statement("ALTER TABLE people ADD CONSTRAINT people_custom_fields_object_chk CHECK (jsonb_typeof(custom_fields) = 'object')");

        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type');
            $table->jsonb('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['church_id', 'key']);
            $table->index('church_id');
        });

        DB::statement("ALTER TABLE custom_field_definitions ADD CONSTRAINT custom_field_definitions_type_chk CHECK (type IN ('text', 'textarea', 'number', 'date', 'boolean', 'select', 'multiselect'))");
        DB::statement("ALTER TABLE custom_field_definitions ADD CONSTRAINT custom_field_definitions_options_type_chk CHECK (options IS NULL OR jsonb_typeof(options) IN ('array', 'object'))");

        Schema::create('segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('church_id');
            $table->unique(['church_id', 'id'], 'segments_church_id_uq');
        });

        DB::statement("ALTER TABLE segments ADD CONSTRAINT segments_rules_object_chk CHECK (rules IS NULL OR jsonb_typeof(rules) = 'object')");

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->index(['church_id', 'starts_at']);
            $table->unique(['church_id', 'id'], 'events_church_id_uq');
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_chk CHECK (status IN ('draft', 'published', 'cancelled', 'completed'))");
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_time_range_chk CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at >= starts_at)');

        Schema::create('event_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('segment_id');
            $table->timestamps();
            $table->unique(['church_id', 'event_id', 'segment_id'], 'event_segments_pair_uq');
            $table->index('church_id');
            $table->foreign(['church_id', 'event_id'], 'event_segments_event_fk')->references(['church_id', 'id'])->on('events')->cascadeOnDelete();
            $table->foreign(['church_id', 'segment_id'], 'event_segments_segment_fk')->references(['church_id', 'id'])->on('segments')->cascadeOnDelete();
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('status')->default('registered');
            $table->unsignedSmallInteger('guest_count')->default(0);
            $table->timestampTz('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'event_id', 'person_id'], 'event_reg_person_uq');
            $table->unique(['church_id', 'id'], 'event_reg_church_id_uq');
            $table->unique(['church_id', 'event_id', 'id'], 'event_reg_church_event_id_uq');
            $table->unique(['church_id', 'event_id', 'id', 'person_id'], 'event_reg_person_identity_uq');
            $table->index('church_id');
            $table->foreign(['church_id', 'event_id'], 'event_reg_event_fk')->references(['church_id', 'id'])->on('events')->cascadeOnDelete();
            $table->foreign(['church_id', 'person_id'], 'event_reg_person_fk')->references(['church_id', 'id'])->on('people');
        });

        DB::statement("ALTER TABLE event_registrations ADD CONSTRAINT event_registrations_status_chk CHECK (status IN ('registered', 'confirmed', 'declined', 'waitlisted', 'cancelled'))");
        DB::statement('ALTER TABLE event_registrations ADD CONSTRAINT event_registrations_guest_count_nonnegative_chk CHECK (guest_count >= 0)');
        DB::statement("ALTER TABLE event_registrations ADD CONSTRAINT event_registrations_identity_chk CHECK (person_id IS NOT NULL OR (guest_name IS NOT NULL AND btrim(guest_name) <> ''))");

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_registration_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('status')->default('present');
            $table->string('source')->default('manual');
            $table->timestampTz('recorded_at')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'event_id']);
            $table->foreign(['church_id', 'event_id'], 'attendance_event_fk')->references(['church_id', 'id'])->on('events')->cascadeOnDelete();
            // With PostgreSQL's default MATCH SIMPLE, a null RSVP id skips this optional composite FK.
            $table->foreign(['church_id', 'event_id', 'event_registration_id'], 'attendance_registration_fk')->references(['church_id', 'event_id', 'id'])->on('event_registrations');
            // When both ids are present, the person must match the person on the RSVP.
            $table->foreign(['church_id', 'event_id', 'event_registration_id', 'person_id'], 'attendance_registration_person_fk')->references(['church_id', 'event_id', 'id', 'person_id'])->on('event_registrations');
            $table->foreign(['church_id', 'person_id'], 'attendance_person_fk')->references(['church_id', 'id'])->on('people');
            $table->foreign(['church_id', 'recorded_by'], 'attendance_recorded_by_fk')->references(['church_id', 'user_id'])->on('church_memberships');
        });

        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_status_chk CHECK (status IN ('present', 'absent', 'late', 'excused'))");
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_source_chk CHECK (source IN ('manual', 'import', 'device'))");
        DB::statement('ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_subject_chk CHECK (person_id IS NOT NULL OR event_registration_id IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX attendance_records_person_uq ON attendance_records (church_id, event_id, person_id) WHERE person_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX attendance_records_registration_uq ON attendance_records (church_id, event_id, event_registration_id) WHERE event_registration_id IS NOT NULL');

        Schema::create('funds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index('church_id');
            $table->unique(['church_id', 'id'], 'funds_church_id_uq');
            $table->unique(['church_id', 'name'], 'funds_church_name_uq');
        });

        DB::statement("ALTER TABLE funds ADD CONSTRAINT funds_status_chk CHECK (status IN ('active', 'archived'))");

        Schema::create('contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('fund_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 12);
            $table->string('method');
            $table->string('status')->default('recorded');
            $table->timestampTz('contributed_at');
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'contributed_at']);
            $table->foreign(['church_id', 'person_id'], 'contrib_person_fk')->references(['church_id', 'id'])->on('people');
            $table->foreign(['church_id', 'fund_id'], 'contrib_fund_fk')->references(['church_id', 'id'])->on('funds');
            $table->foreign(['church_id', 'currency_code'], 'contrib_currency_fk')->references(['church_id', 'currency_code'])->on('church_currencies');
        });

        // PostgreSQL does not guarantee non-negative values for unsignedBigInteger; enforce it explicitly.
        DB::statement('ALTER TABLE contributions ADD CONSTRAINT contributions_amount_minor_nonnegative CHECK (amount_minor >= 0)');
        DB::statement("ALTER TABLE contributions ADD CONSTRAINT contributions_status_chk CHECK (status IN ('recorded', 'voided', 'reconciled'))");
        DB::statement("ALTER TABLE contributions ADD CONSTRAINT contributions_method_chk CHECK (method IN ('cash', 'transfer', 'card', 'mobile_payment', 'other'))");

        Schema::create('pipeline_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('church_id');
            $table->unique(['church_id', 'id'], 'pipeline_stages_church_id_uq');
            $table->unique(['church_id', 'name'], 'pipeline_stages_church_name_uq');
        });

        DB::statement('ALTER TABLE pipeline_stages ADD CONSTRAINT pipeline_stages_position_nonnegative_chk CHECK (position >= 0)');

        Schema::create('pastoral_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('pipeline_stage_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('status')->default('open');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'status']);
            $table->foreign(['church_id', 'person_id'], 'followup_person_fk')->references(['church_id', 'id'])->on('people');
            $table->foreign(['church_id', 'pipeline_stage_id'], 'followup_stage_fk')->references(['church_id', 'id'])->on('pipeline_stages');
            $table->foreign(['church_id', 'assigned_to'], 'followup_assignee_fk')->references(['church_id', 'user_id'])->on('church_memberships');
        });

        DB::statement("ALTER TABLE pastoral_follow_ups ADD CONSTRAINT pastoral_follow_ups_status_chk CHECK (status IN ('open', 'in_progress', 'completed', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pastoral_follow_ups');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('funds');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_segments');
        Schema::dropIfExists('events');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('custom_field_definitions');
        DB::statement('DROP INDEX IF EXISTS people_custom_fields_gin');
        Schema::dropIfExists('people');
    }
};
