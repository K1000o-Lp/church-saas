<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('person_id');
            $table->unsignedBigInteger('related_person_id');
            $table->string('relationship');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index('church_id');
            $table->index(['church_id', 'person_id']);
            $table->unique(
                ['church_id', 'person_id', 'related_person_id', 'relationship'],
                'person_relationships_unique',
            );
            $table->foreign(['church_id', 'person_id'], 'person_relationships_person_fk')
                ->references(['church_id', 'id'])->on('people')->cascadeOnDelete();
            $table->foreign(['church_id', 'related_person_id'], 'person_relationships_related_fk')
                ->references(['church_id', 'id'])->on('people')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE person_relationships ADD CONSTRAINT person_relationships_type_chk CHECK (relationship IN ('spouse', 'parent', 'child', 'sibling', 'guardian', 'other'))");
        DB::statement('ALTER TABLE person_relationships ADD CONSTRAINT person_relationships_distinct_chk CHECK (person_id <> related_person_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('person_relationships');
    }
};
