<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            // Who the code was sent to — an email or a phone number. Not a
            // user_id: a code is often requested before we know whether an
            // account exists, and it must behave identically either way.
            $table->string('identifier');
            $table->string('channel', 32)->default('email');
            $table->string('purpose', 32)->default('login');
            // Hashed. A leaked database read should not hand someone a working
            // code, and a code is a credential for the seconds it lives.
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
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
            $table->timestamp('consumed_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();

            $table->index(['identifier', 'purpose', 'consumed_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
