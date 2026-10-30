<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Requires PostgreSQL: numeric(14,2) keeps exact monetary values with two
// decimals. This migration runs before the plan matrix seed so it can use the
// new `price` column.
return new class extends Migration
{
    public function up(): void
    {
        $this->convertToDecimal('plans', 'price_minor', 'price', 'plans_price_minor_nonnegative_chk', 'plans_price_nonnegative_chk', '>= 0');
        $this->convertToDecimal('contributions', 'amount_minor', 'amount', 'contributions_amount_minor_nonnegative', 'contributions_amount_nonnegative_chk', '>= 0');
    }

    public function down(): void
    {
        $this->convertToMinor('plans', 'price', 'price_minor', 'plans_price_nonnegative_chk', 'plans_price_minor_nonnegative_chk', '>= 0');
        $this->convertToMinor('contributions', 'amount', 'amount_minor', 'contributions_amount_nonnegative_chk', 'contributions_amount_minor_nonnegative', '>= 0');
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
