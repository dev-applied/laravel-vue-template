<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('level', 16)->default('info');
            $table->string('placement', 16)->default('banner');
            // Free-form: the project's own AudienceResolver decides what this
            // string means. The module never assumes roles exist.
            $table->string('audience')->default('everyone');
            $table->boolean('dismissible')->default(true);
            // A required announcement must be acknowledged, not just closed —
            // it is how a policy change gets a defensible record.
            $table->boolean('requires_acknowledgement')->default(false);
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // The hot query is "what is live right now" — published, within
            // window — so it gets the composite index.
            $table->index(['published_at', 'starts_at', 'ends_at']);
        });

        Schema::create('announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            // One row per person per announcement. Without this a double-click
            // on Dismiss writes two rows and the count-based reporting lies.
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
    }
};
