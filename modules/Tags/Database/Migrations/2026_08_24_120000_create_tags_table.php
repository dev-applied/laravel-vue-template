<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            // Normalised for matching. "Urgent", "urgent" and " URGENT " are
            // one tag — otherwise a tag list becomes three near-duplicates
            // nobody can filter on reliably.
            $table->string('slug', 60)->unique();
            $table->string('color', 24)->nullable();
            // Scopes a tag to a kind of record so an "urgent" on orders and an
            // "urgent" on tickets can be different tags if a project wants
            // that. Null means the tag is global.
            $table->string('type', 64)->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['type', 'name']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamps();

            // Tagging the same record twice is a no-op, not two rows.
            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique');
            // No index on (taggable_type, taggable_id) here — morphs() already
            // made one, and declaring it again is a duplicate-index error.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
