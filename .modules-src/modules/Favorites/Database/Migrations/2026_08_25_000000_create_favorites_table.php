<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('favorites')) {
            return;
        }

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('favoritable');
            $table->timestamps();

            // The uniqueness IS the feature. A favourite is a set membership,
            // not an event: without this a double-tap or two tabs leave two
            // rows, the list shows duplicates, and un-favouriting removes one
            // of them and looks broken.
            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'favorites_unique');

            // Answers "what has this user starred", newest first, without a
            // filesort — the only query the list page makes.
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
