<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Requires PostgreSQL for timestampTz and CHECK constraints used by billing.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            // Billing history survives a church purge: the FK is nullable and the
            // church identity is snapshotted for later reconciliation.
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->string('church_name');
            $table->string('church_slug');
            $table->string('number')->unique();
            $table->string('status')->default('issued');
            $table->char('currency_code', 3)->default('USD');
            $table->unsignedBigInteger('amount_minor');
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('issued_at');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('issued_by_platform_admin_id')
                ->nullable()
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->timestamps();
            $table->index(['church_id', 'status']);
            $table->index(['status', 'due_at']);
            $table->index(['church_id', 'issued_at']);
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_chk CHECK (status IN ('issued', 'paid', 'void', 'overdue'))");
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_amount_positive_chk CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_currency_usd_chk CHECK (currency_code = 'USD')");
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_number_not_blank_chk CHECK (btrim(number) <> \'\')');

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3)->default('USD');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('paid_at');
            $table->foreignId('recorded_by_platform_admin_id')
                ->nullable()
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->timestamps();
            $table->index(['church_id', 'paid_at']);
            $table->index(['invoice_id', 'paid_at']);
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_chk CHECK (method IN ('cash', 'transfer', 'card', 'mobile_payment', 'other'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive_chk CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_currency_usd_chk CHECK (currency_code = 'USD')");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
    }
};
