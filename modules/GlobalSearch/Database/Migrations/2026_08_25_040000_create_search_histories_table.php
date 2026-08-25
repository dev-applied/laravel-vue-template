<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('term');
            $table->unsignedInteger('result_count')->default(0);
            $table->timestamps();

            // The pair is unique because a repeat search updates its row rather
            // than appending one — see SearchHistory::remember(). Enforced here
            // as well so a concurrent double-submit cannot leave two.
            $table->unique(['user_id', 'term']);

            // The only read is "this user's, newest first".
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_histories');
    }
};
