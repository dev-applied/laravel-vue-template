<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_opt_outs', function (Blueprint $table) {
            $table->id();
            // Unique, and always E.164 — see PhoneNumber. A list keyed on
            // whatever format somebody typed cannot be matched against.
            $table->string('phone_number', 20)->unique();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->index();

            // The body is stored because support cannot answer "what did we
            // send them" otherwise. Anything secret — an OTP code is the
            // obvious one — should be redacted by the caller before it reaches
            // here; see the README.
            $table->text('body');

            $table->string('status', 20)->index();
            $table->string('driver', 40)->nullable();
            $table->string('vendor_id')->nullable()->index();
            $table->text('error')->nullable();

            // Optional link back to whoever it was about, without this module
            // needing to know what a User is.
            $table->nullableMorphs('notifiable');

            $table->timestamps();
            $table->index(['phone_number', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_opt_outs');
    }
};
