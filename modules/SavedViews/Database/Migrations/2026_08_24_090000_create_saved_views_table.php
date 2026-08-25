<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Which screen this view belongs to, e.g. "items.index". A view for
            // one table must never surface on another.
            $table->string('key', 128);
            $table->string('name', 120);
            // Filters, sort and column prefs together. JSON because the shape
            // is the screen's business, not this module's.
            $table->json('payload');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // The read query is always "my views for this screen", plus the
            // shared ones.
            $table->index(['user_id', 'key', 'position']);
            $table->index(['key', 'is_shared']);
            // Two views on the same screen with the same name is a naming
            // mistake, not a feature — the picker would show them identically.
            $table->unique(['user_id', 'key', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
