<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_entitlements', function (Blueprint $table) {
            // The high-water mark of the event this row's access was resolved
            // from. Webhooks are not ordered — RevenueCat retries independently
            // per event, so an EXPIRATION queued behind a failed delivery can
            // land after the INITIAL_PURCHASE that superseded it.
            //
            // Milliseconds, matching the payload, and NOT a `timestamp` column:
            // this is a comparison key against a vendor's clock, not our own
            // record of when something happened, and rounding it to seconds
            // would make same-second events falsely equal.
            $table->unsignedBigInteger('last_event_at_ms')->nullable()->after('trial_used');
        });

        Schema::table('revenuecat_webhook_events', function (Blueprint $table) {
            // On the ledger for diagnosis. The full payload is already stored,
            // but it is a JSON column — asking "what order did these actually
            // happen in" should not require parsing every row.
            $table->unsignedBigInteger('event_at_ms')->nullable()->after('environment');
            $table->index('event_at_ms');
        });
    }

    public function down(): void
    {
        Schema::table('user_entitlements', function (Blueprint $table) {
            $table->dropColumn('last_event_at_ms');
        });

        Schema::table('revenuecat_webhook_events', function (Blueprint $table) {
            $table->dropIndex(['event_at_ms']);
            $table->dropColumn('event_at_ms');
        });
    }
};
