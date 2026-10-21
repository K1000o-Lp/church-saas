<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('segment_id');
            $table->unsignedBigInteger('person_id');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('church_id');
            $table->index(['church_id', 'person_id']);
            $table->unique(['church_id', 'segment_id', 'person_id'], 'segment_members_unique');
            $table->foreign(['church_id', 'segment_id'], 'segment_members_segment_fk')
                ->references(['church_id', 'id'])->on('segments')->cascadeOnDelete();
            $table->foreign(['church_id', 'person_id'], 'segment_members_person_fk')
                ->references(['church_id', 'id'])->on('people')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_members');
    }
};
