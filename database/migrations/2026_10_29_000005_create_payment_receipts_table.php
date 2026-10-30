<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Requires PostgreSQL for numeric money, timestampTz, partial indexes and CHECK constraints.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->index('public_token_hash', 'event_reg_public_token_idx');
        });

        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_registration_id');
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 12);
            $table->string('reference_number')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('status')->default('pending');
            $table->timestampTz('submitted_at');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'status'], 'payment_receipts_church_status_idx');
            $table->unique(['church_id', 'id'], 'payment_receipts_church_id_uq');
            $table->foreign(['church_id', 'event_registration_id'], 'payment_receipts_registration_fk')
                ->references(['church_id', 'id'])->on('event_registrations')->cascadeOnDelete();
            $table->foreign(['church_id', 'currency_code'], 'payment_receipts_currency_fk')
                ->references(['church_id', 'currency_code'])->on('church_currencies');
            $table->foreign(['church_id', 'verified_by'], 'payment_receipts_verifier_fk')
                ->references(['church_id', 'user_id'])->on('church_memberships');
        });

        DB::statement('ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_amount_positive_chk CHECK (amount > 0)');
        DB::statement("ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_status_chk CHECK (status IN ('pending', 'verified', 'rejected'))");
        DB::statement("ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_mime_chk CHECK (mime_type IN ('image/jpeg', 'image/png', 'image/webp', 'application/pdf'))");
        DB::statement('ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_size_positive_chk CHECK (size > 0)');
        DB::statement("CREATE UNIQUE INDEX payment_receipts_one_pending_uq ON payment_receipts (event_registration_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropIndex('event_reg_public_token_idx');
        });
    }
};
