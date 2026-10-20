<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('currency_code', 3)->default('USD');
            $table->string('billing_interval', 16)->default('month');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_price_minor_nonnegative_chk CHECK (price_minor >= 0)');
        DB::statement("ALTER TABLE plans ADD CONSTRAINT plans_billing_interval_chk CHECK (billing_interval IN ('month', 'year'))");

        Schema::create('plan_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('resource');
            $table->unsignedBigInteger('limit_value')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'resource']);
        });

        DB::statement("ALTER TABLE plan_limits ADD CONSTRAINT plan_limits_resource_chk CHECK (resource IN ('people', 'active_users', 'currencies', 'events_per_month', 'workflows', 'executions_per_month', 'integrations'))");
        DB::statement('ALTER TABLE plan_limits ADD CONSTRAINT plan_limits_value_nonnegative_chk CHECK (limit_value IS NULL OR limit_value >= 0)');

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->timestamps();
            $table->unique(['plan_id', 'feature']);
        });

        DB::statement("ALTER TABLE plan_features ADD CONSTRAINT plan_features_feature_chk CHECK (feature IN ('multi_currency', 'volunteers', 'pastoral_pipeline', 'white_label', 'automation', 'payments'))");

        Schema::create('church_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('plan_changed_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by_platform_admin_id')
                ->nullable()
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE church_subscriptions ADD CONSTRAINT church_subscriptions_status_chk CHECK (status IN ('active', 'past_due', 'cancelled'))");

        Schema::create('church_limit_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('resource');
            $table->unsignedBigInteger('limit_value')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_platform_admin_id')
                ->nullable()
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['church_id', 'resource']);
        });

        DB::statement("ALTER TABLE church_limit_overrides ADD CONSTRAINT church_limit_overrides_resource_chk CHECK (resource IN ('people', 'active_users', 'currencies', 'events_per_month', 'workflows', 'executions_per_month', 'integrations'))");
        DB::statement('ALTER TABLE church_limit_overrides ADD CONSTRAINT church_limit_overrides_value_nonnegative_chk CHECK (limit_value IS NULL OR limit_value >= 0)');

        Schema::create('church_feature_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->boolean('is_enabled');
            $table->text('note')->nullable();
            $table->foreignId('created_by_platform_admin_id')
                ->nullable()
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['church_id', 'feature']);
        });

        DB::statement("ALTER TABLE church_feature_overrides ADD CONSTRAINT church_feature_overrides_feature_chk CHECK (feature IN ('multi_currency', 'volunteers', 'pastoral_pipeline', 'white_label', 'automation', 'payments'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('church_feature_overrides');
        Schema::dropIfExists('church_limit_overrides');
        Schema::dropIfExists('church_subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plan_limits');
        Schema::dropIfExists('plans');
    }
};
