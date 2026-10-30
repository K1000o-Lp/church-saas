<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Requires PostgreSQL for numeric money, timestampTz and CHECK constraints.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('slug')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->decimal('price', 14, 2)->nullable();
            $table->string('currency_code', 12)->nullable();
            $table->timestampTz('registration_opens_at')->nullable();
            $table->timestampTz('registration_closes_at')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->unique(['church_id', 'slug'], 'events_church_slug_uq');
            $table->index(['church_id', 'is_public', 'status'], 'events_public_status_idx');
            $table->foreign(['church_id', 'currency_code'], 'events_currency_fk')
                ->references(['church_id', 'currency_code'])->on('church_currencies');
        });

        DB::statement('ALTER TABLE events ADD CONSTRAINT events_capacity_positive_chk CHECK (capacity IS NULL OR capacity > 0)');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_paid_requires_price_chk CHECK (is_paid = false OR (price IS NOT NULL AND price >= 0 AND currency_code IS NOT NULL))');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_registration_window_chk CHECK (registration_opens_at IS NULL OR registration_closes_at IS NULL OR registration_closes_at >= registration_opens_at)');

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->string('payment_status')->default('not_required');
            $table->string('public_token_hash', 64)->nullable();
            $table->string('ticket_code_hash', 64)->nullable()->unique();
            $table->timestampTz('issued_at')->nullable();
            $table->index(['church_id', 'event_id', 'payment_status'], 'event_reg_payment_status_idx');
        });

        DB::statement("ALTER TABLE event_registrations ADD CONSTRAINT event_registrations_payment_status_chk CHECK (payment_status IN ('not_required', 'pending', 'under_review', 'paid', 'rejected'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE event_registrations DROP CONSTRAINT IF EXISTS event_registrations_payment_status_chk');

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropUnique(['ticket_code_hash']);
            $table->dropIndex('event_reg_payment_status_idx');
            $table->dropColumn(['payment_status', 'public_token_hash', 'ticket_code_hash', 'issued_at']);
        });

        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_registration_window_chk');
        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_paid_requires_price_chk');
        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_capacity_positive_chk');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign('events_currency_fk');
            $table->dropUnique('events_church_slug_uq');
            $table->dropIndex('events_public_status_idx');
            $table->dropColumn([
                'slug',
                'capacity',
                'is_public',
                'is_paid',
                'price',
                'currency_code',
                'registration_opens_at',
                'registration_closes_at',
                'cover_image_path',
            ]);
        });
    }
};
