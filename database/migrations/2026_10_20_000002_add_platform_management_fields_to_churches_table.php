<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE churches DROP CONSTRAINT churches_status_chk');
        DB::statement("ALTER TABLE churches ADD CONSTRAINT churches_status_chk CHECK (status IN ('active', 'inactive', 'suspended', 'archived'))");

        Schema::table('churches', function (Blueprint $table): void {
            $table->text('status_reason')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by_platform_admin_id')
                ->nullable()
                ->after('status_changed_at')
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->index(['status', 'archived_at']);
        });
    }

    public function down(): void
    {
        DB::table('churches')->where('status', 'archived')->update(['status' => 'inactive']);

        DB::statement('ALTER TABLE churches DROP CONSTRAINT churches_status_chk');
        DB::statement("ALTER TABLE churches ADD CONSTRAINT churches_status_chk CHECK (status IN ('active', 'inactive', 'suspended'))");

        Schema::table('churches', function (Blueprint $table): void {
            $table->dropForeign(['status_changed_by_platform_admin_id']);
            $table->dropIndex(['status', 'archived_at']);
            $table->dropColumn([
                'status_reason',
                'status_changed_at',
                'status_changed_by_platform_admin_id',
                'archived_at',
            ]);
        });
    }
};
