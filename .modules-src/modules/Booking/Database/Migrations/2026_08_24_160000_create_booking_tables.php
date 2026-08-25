<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // Availability is authored in this timezone. Storing only UTC and
            // assuming the app timezone means every slot shifts by an hour
            // twice a year.
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            // Gap enforced after each booking — cleaning, travel, notes.
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            // How many bookings may overlap. 1 is an appointment; more is a
            // class or a room with seats.
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->unsignedSmallInteger('min_notice_minutes')->default(0);
            $table->unsignedSmallInteger('advance_days')->default(60);
            $table->boolean('is_active')->default(true);
            $table->whoDidIt();
            $table->timestamps();
        });

        Schema::create('resource_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookable_resource_id')->constrained()->cascadeOnDelete();
            // 0 = Sunday, matching Carbon's dayOfWeek.
            $table->unsignedTinyInteger('day_of_week');
            // Local wall-clock in the resource's timezone, not UTC.
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->index(['bookable_resource_id', 'day_of_week'], 'availability_resource_day_index');
        });

        Schema::create('resource_blackouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookable_resource_id')->constrained()->cascadeOnDelete();
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
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['bookable_resource_id', 'starts_at', 'ends_at'], 'blackouts_resource_window_index');
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookable_resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 12)->unique();
            $table->string('name');
            $table->string('email');
            $table->text('notes')->nullable();
            // UTC. Always.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 16)->default('confirmed');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // The overlap query runs on every booking attempt and every
            // availability lookup.
            $table->index(['bookable_resource_id', 'starts_at', 'ends_at'], 'bookings_resource_window_index');
            $table->index(['bookable_resource_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('resource_blackouts');
        Schema::dropIfExists('resource_availability');
        Schema::dropIfExists('bookable_resources');
    }
};
