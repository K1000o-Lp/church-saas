<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Requires PostgreSQL for timestampTz and CHECK constraints.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('person_id');
            $table->unsignedBigInteger('host_user_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('rescheduled_from_id')->nullable();
            $table->timestampTz('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('status')->default('scheduled');
            $table->string('subject')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'scheduled_at']);
            $table->index(['church_id', 'host_user_id']);
            $table->unique(['church_id', 'id'], 'appointments_church_id_uq');
            $table->foreign(['church_id', 'person_id'], 'appointments_person_fk')
                ->references(['church_id', 'id'])->on('people');
            $table->foreign(['church_id', 'host_user_id'], 'appointments_host_fk')
                ->references(['church_id', 'user_id'])->on('church_memberships');
            $table->foreign(['church_id', 'created_by'], 'appointments_created_by_fk')
                ->references(['church_id', 'user_id'])->on('church_memberships');
            $table->foreign(['church_id', 'rescheduled_from_id'], 'appointments_rescheduled_from_fk')
                ->references(['church_id', 'id'])->on('appointments')->nullOnDelete();
        });

        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_chk CHECK (status IN ('scheduled', 'rescheduled', 'completed', 'cancelled', 'no_show'))");
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_duration_chk CHECK (duration_minutes > 0 AND duration_minutes <= 480)');
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
