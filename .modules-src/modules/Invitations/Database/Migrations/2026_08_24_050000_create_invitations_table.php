<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            // SHA-256 of the token, never the token itself. A leaked database
            // must not hand the reader a working set of invite links.
            $table->string('token_hash', 64)->unique();
            $table->string('role')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // dateTime, not timestamp. A TIMESTAMP column declared NOT NULL with
            // no explicit default gets silently rewritten by MySQL/MariaDB when
            // explicit_defaults_for_timestamp is off (the MariaDB default): the
            // FIRST such column in a table is given
            // `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`, and every
            // one after it `DEFAULT '0000-00-00'` — which NO_ZERO_DATE then
            // rejects outright, so the migration simply fails.
            //
            // The loud half is the lucky half. The quiet one is ON UPDATE: any
            // write to the row silently pushes the value to now(). For an
            // `expires_at` that inverts the field's entire purpose — see the
            // regression test in this module.
            //
            // DATETIME has none of that behaviour, and is not bounded by 2038.
            $table->dateTime('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // Only ONE invitation per email may be outstanding; resending
            // revokes the previous one rather than leaving two live tokens.
            $table->index(['email', 'accepted_at', 'revoked_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
