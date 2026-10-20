<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token_hash', 64)->unique();
            $table->string('status')->default('pending');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'status']);
            $table->index(['email', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX church_invitations_pending_email_uq ON church_invitations (church_id, lower(email)) WHERE status = 'pending'");
        DB::statement("ALTER TABLE church_invitations ADD CONSTRAINT church_invitations_status_chk CHECK (status IN ('pending', 'accepted', 'revoked', 'expired'))");
        DB::statement("ALTER TABLE church_invitations ADD CONSTRAINT church_invitations_role_chk CHECK (role IN ('admin', 'pastor', 'finance', 'leader', 'volunteer', 'member'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('church_invitations');
    }
};
