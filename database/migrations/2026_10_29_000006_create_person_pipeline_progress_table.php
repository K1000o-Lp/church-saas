<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_pipeline_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('person_id');
            $table->unsignedBigInteger('pipeline_stage_id');
            $table->string('status')->default('in_progress');
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamps();
            $table->unique(
                ['church_id', 'person_id', 'pipeline_stage_id'],
                'person_pipeline_progress_unique',
            );
            $table->index(['church_id', 'pipeline_stage_id'], 'person_pipeline_progress_stage_idx');
            $table->unique(['church_id', 'id'], 'person_pipeline_progress_church_id_uq');
            $table->foreign(['church_id', 'person_id'], 'pipeline_progress_person_fk')
                ->references(['church_id', 'id'])->on('people')->cascadeOnDelete();
            $table->foreign(['church_id', 'pipeline_stage_id'], 'pipeline_progress_stage_fk')
                ->references(['church_id', 'id'])->on('pipeline_stages')->cascadeOnDelete();
            $table->foreign(['church_id', 'completed_by'], 'pipeline_progress_completed_by_fk')
                ->references(['church_id', 'user_id'])->on('church_memberships');
        });

        DB::statement("ALTER TABLE person_pipeline_progress ADD CONSTRAINT person_pipeline_progress_status_chk CHECK (status IN ('in_progress', 'completed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('person_pipeline_progress');
    }
};
