<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // One level only. Arbitrary nesting produces threads nobody can
            // read and a recursive query nobody can index.
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            // An internal note is staff-only and must never surface to the
            // person the record is about.
            $table->boolean('is_internal')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id', 'created_at'], 'comments_thread_index');
        });

        Schema::create('comment_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Mentioning someone twice in one comment notifies them once.
            $table->unique(['comment_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_mentions');
        Schema::dropIfExists('comments');
    }
};
