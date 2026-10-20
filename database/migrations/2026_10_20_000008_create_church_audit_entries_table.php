<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['church_id', 'created_at']);
            $table->index(['church_id', 'action']);
            $table->index(['church_id', 'subject_type', 'subject_id']);
        });

        DB::statement("ALTER TABLE church_audit_entries ADD CONSTRAINT church_audit_entries_metadata_object_chk CHECK (jsonb_typeof(metadata) = 'object')");
    }

    public function down(): void
    {
        Schema::dropIfExists('church_audit_entries');
    }
};
