<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Requires PostgreSQL for JSONB workflow definitions and run payloads.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_type');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index('church_id');
            $table->unique(['church_id', 'id'], 'workflows_church_id_uq');
            $table->unique(['church_id', 'name']);
        });

        DB::statement("ALTER TABLE workflows ADD CONSTRAINT workflows_trigger_type_not_blank_chk CHECK (btrim(trigger_type) <> '')");

        Schema::create('workflow_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedInteger('version');
            $table->jsonb('definition');
            $table->timestamps();
            $table->unique(['church_id', 'workflow_id', 'version'], 'workflow_version_number_uq');
            $table->unique(['church_id', 'workflow_id', 'id'], 'workflow_version_identity_uq');
            $table->foreign(['church_id', 'workflow_id'], 'workflow_version_workflow_fk')->references(['church_id', 'id'])->on('workflows')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE workflow_versions ADD CONSTRAINT workflow_versions_version_positive_chk CHECK (version >= 1)');
        DB::statement("ALTER TABLE workflow_versions ADD CONSTRAINT workflow_versions_definition_object_chk CHECK (jsonb_typeof(definition) = 'object')");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_workflow_version_mutation() RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND pg_trigger_depth() > 1 THEN
                    -- A nested FK cascade is allowed for parent Workflow/Church deletion; direct version deletes run at depth 1.
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'workflow_versions rows are immutable; create a new version instead'
                    USING ERRCODE = '55000';
            END;
            $$;

            DROP TRIGGER IF EXISTS workflow_versions_immutable ON workflow_versions;

            CREATE TRIGGER workflow_versions_immutable
                BEFORE UPDATE OR DELETE ON workflow_versions
                FOR EACH ROW EXECUTE FUNCTION prevent_workflow_version_mutation();
            SQL);

        Schema::create('workflow_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('workflow_version_id');
            $table->string('status')->default('pending');
            $table->unsignedInteger('current_node_index')->default(0);
            $table->jsonb('context')->nullable();
            $table->jsonb('errors')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'status']);
            $table->unique(['church_id', 'id'], 'workflow_runs_church_id_uq');
            $table->foreign(['church_id', 'workflow_id', 'workflow_version_id'], 'workflow_run_version_fk')->references(['church_id', 'workflow_id', 'id'])->on('workflow_versions')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE workflow_runs ADD CONSTRAINT workflow_runs_status_chk CHECK (status IN ('pending', 'running', 'completed', 'failed', 'cancelled'))");
        DB::statement('ALTER TABLE workflow_runs ADD CONSTRAINT workflow_runs_current_node_index_nonnegative_chk CHECK (current_node_index >= 0)');

        Schema::create('workflow_node_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('workflow_run_id');
            $table->string('node_id');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('idempotency_key');
            $table->string('status')->default('pending');
            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();
            $table->jsonb('error')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'idempotency_key']);
            $table->index(['church_id', 'workflow_run_id', 'status']);
            $table->foreign(['church_id', 'workflow_run_id'], 'workflow_node_run_fk')->references(['church_id', 'id'])->on('workflow_runs')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE workflow_node_executions ADD CONSTRAINT workflow_node_executions_status_chk CHECK (status IN ('pending', 'queued', 'running', 'waiting', 'completed', 'failed', 'cancelled'))");
        DB::statement('ALTER TABLE workflow_node_executions ADD CONSTRAINT workflow_node_executions_attempts_nonnegative_chk CHECK (attempts >= 0)');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS workflow_versions_immutable ON workflow_versions');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_workflow_version_mutation()');

        Schema::dropIfExists('workflow_node_executions');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflows');
    }
};
