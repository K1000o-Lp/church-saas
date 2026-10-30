<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Requires PostgreSQL. Runs after the invoice/payment tables are created and
// converts their integer minor-unit columns to exact decimals.
return new class extends Migration
{
    public function up(): void
    {
        $this->convertToDecimal('invoices', 'amount_minor', 'amount', 'invoices_amount_positive_chk', 'invoices_amount_positive_chk', '> 0');
        $this->convertToDecimal('payments', 'amount_minor', 'amount', 'payments_amount_positive_chk', 'payments_amount_positive_chk', '> 0');
    }

    public function down(): void
    {
        $this->convertToMinor('invoices', 'amount', 'amount_minor', 'invoices_amount_positive_chk', 'invoices_amount_positive_chk', '> 0');
        $this->convertToMinor('payments', 'amount', 'amount_minor', 'payments_amount_positive_chk', 'payments_amount_positive_chk', '> 0');
    }

    private function convertToDecimal(
        string $table,
        string $from,
        string $to,
        string $oldConstraint,
        string $newConstraint,
        string $check,
    ): void {
        DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $oldConstraint));
        DB::statement(sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $table, $from, $to));
        DB::statement(sprintf(
            'ALTER TABLE %s ALTER COLUMN %s TYPE numeric(14,2) USING (%s / 100.0)',
            $table,
            $to,
            $to,
        ));
        DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT 0', $table, $to));
        DB::statement(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s %s)',
            $table,
            $newConstraint,
            $to,
            $check,
        ));
    }

    private function convertToMinor(
        string $table,
        string $from,
        string $to,
        string $oldConstraint,
        string $newConstraint,
        string $check,
    ): void {
        DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $oldConstraint));
        DB::statement(sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $table, $from, $to));
        DB::statement(sprintf(
            'ALTER TABLE %s ALTER COLUMN %s TYPE bigint USING (round(%s * 100)::bigint)',
            $table,
            $to,
            $to,
        ));
        DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT 0', $table, $to));
        DB::statement(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s %s)',
            $table,
            $newConstraint,
            $to,
            $check,
        ));
    }
};
