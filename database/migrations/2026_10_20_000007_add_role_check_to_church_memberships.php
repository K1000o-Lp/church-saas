<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE church_memberships ADD CONSTRAINT church_memberships_role_chk CHECK (role IN ('admin', 'pastor', 'finance', 'leader', 'volunteer', 'member'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE church_memberships DROP CONSTRAINT IF EXISTS church_memberships_role_chk');
    }
};
