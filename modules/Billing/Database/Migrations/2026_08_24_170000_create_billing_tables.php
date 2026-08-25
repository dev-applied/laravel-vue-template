<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per user holding RESOLVED access. Everything a gate needs
        // must be readable without calling RevenueCat — an outage at the
        // vendor must not become an outage of the product.
        Schema::create('user_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('tier', 32)->default('free');
            $table->string('status', 32)->default('none');
            $table->string('plan', 32)->default('none');
            // Stored because management routing follows the PROCESSOR, not the
            // device: someone who bought on iOS still manages through Apple
            // even while sitting on the web.
            $table->string('provider', 32)->default('none');
            $table->string('provider_subscription_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            // A resolved `free` is ambiguous. This is what separates "never
            // subscribed" from "trial expired", which need different copy and
            // different walls.
            $table->boolean('trial_used')->default(false);
            $table->timestamps();

            $table->index(['tier', 'status']);
        });

        // The idempotency ledger. RevenueCat retries until it gets a 2xx, and
        // the unique event id is what makes a replay cheap instead of a double
        // grant.
        Schema::create('revenuecat_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type', 64);
            $table->string('app_user_id')->nullable();
            $table->string('environment', 32)->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenuecat_webhook_events');
        Schema::dropIfExists('user_entitlements');
    }
};
