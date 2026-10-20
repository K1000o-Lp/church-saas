<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Requires PostgreSQL for the JSONB metadata column.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_id')
                ->nullable()
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->unsignedBigInteger('church_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['church_id', 'created_at']);
            $table->index(['platform_admin_id', 'created_at']);
        });

        DB::statement("ALTER TABLE platform_audit_entries ADD CONSTRAINT platform_audit_entries_metadata_object_chk CHECK (jsonb_typeof(metadata) = 'object')");
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_entries');
    }
};
