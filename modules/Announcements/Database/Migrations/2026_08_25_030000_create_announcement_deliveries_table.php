<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per recipient actually mailed, claimed BEFORE the send.
        //
        // The job retries, and a retry restarted at the first recipient and
        // mailed everyone again — the job's own comment said so. Publishing
        // twice did the same thing by a different route. Both become harmless
        // once "has this address already been told" is a fact in the database
        // rather than a position in a loop.
        //
        // Claimed before rather than after on purpose. A crash in the gap
        // between claiming and sending costs one person one email, and they
        // still see the announcement in-app; a crash in the gap the other way
        // round mails the entire user base a second time. At-most-once is the
        // right guarantee for a broadcast.
        Schema::create('announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('recipient');
            $table->timestamp('sent_at')->nullable();

            $table->unique(['announcement_id', 'recipient']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_deliveries');
    }
};
