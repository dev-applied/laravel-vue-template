<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Models\RevenueCatWebhookEvent;
use Modules\Billing\Services\EntitlementWriter;
use Modules\Billing\Services\MappedEvent;
use Modules\Billing\Services\RevenueCatEventMapper;
use Modules\Billing\Services\TransferResolver;
use Throwable;

/**
 * The only thing in the system that grants access.
 *
 * A purchase call returning "success" on the client is a signal to RE-READ
 * server state, never a fact about access. The web purchase is client-driven
 * and therefore forgeable; the native one is not much better from here.
 */
class RevenueCatWebhookController extends Controller
{
    public function __construct(
        private readonly RevenueCatEventMapper $mapper,
        private readonly EntitlementWriter $writer,
        private readonly TransferResolver $transfers,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['message' => 'Unauthorised.'], 401);
        }

        $event   = (array) $request->input('event', []);
        $eventId = $event['id'] ?? null;

        if (! $eventId) {
            return response()->json(['message' => 'No event id.'], 422);
        }

        // ── The ordering trap ────────────────────────────────────────────────
        // Map and run every ignore-check BEFORE claiming the ledger row.
        //
        // Claiming first and then deciding to ignore permanently burns that
        // event id, and there is no resend button. The events that get ignored
        // are exactly the ones most worth replaying: one with no attributable
        // user, or a sandbox event received while sandbox granting is off.
        $mapped = $this->mapper->map($event);

        if ($mapped->isSandbox && ! config('billing.allow_sandbox', false)) {
            Log::info('billing: sandbox event ignored', ['event_id' => $eventId, 'type' => $event['type'] ?? null]);

            return response()->json(['message' => 'Sandbox event ignored.']);
        }

        if ($mapped->kind === MappedEvent::KIND_INERT) {
            Log::info('billing: inert event', [
                'event_id' => $eventId,
                'type'     => $event['type'] ?? null,
                'reason'   => $mapped->reason,
            ]);

            return response()->json(['message' => 'Nothing to do.']);
        }

        // Now claim. A duplicate delivery short-circuits here.
        $claim = RevenueCatWebhookEvent::firstOrCreate(
            ['event_id' => $eventId],
            [
                'event_type'  => mb_strtoupper((string) ($event['type'] ?? 'UNKNOWN')),
                'app_user_id' => $event['app_user_id'] ?? null,
                'environment' => $event['environment'] ?? null,
                'event_at_ms' => $mapped->eventAtMs,
                'payload'     => $event,
            ]
        );

        if (! $claim->wasRecentlyCreated) {
            return response()->json(['message' => 'Already handled.']);
        }

        try {
            $this->handle($mapped);
        } catch (Throwable $e) {
            // Release the claim before returning a 5xx. Otherwise the retry we
            // just asked for is rejected as a duplicate and the purchase is
            // lost — silently, and in the customer's favour never.
            $claim->delete();

            report($e);

            return response()->json(['message' => 'Could not process the event.'], 500);
        }

        return response()->json(['message' => 'Handled.']);
    }

    private function handle(MappedEvent $mapped): void
    {
        if ($mapped->kind === MappedEvent::KIND_TRANSFER) {
            $this->transfers->resolve($mapped);

            return;
        }

        if ($mapped->userId !== null) {
            $this->writer->apply($mapped->userId, $mapped->patch, $mapped->eventAtMs);
        }
    }

    /**
     * Constant-time comparison against the shared secret.
     *
     * An UNCONFIGURED secret rejects everything. This is the one place where a
     * permissive default is catastrophic — an open webhook endpoint lets anyone
     * grant themselves any tier.
     */
    private function authorised(Request $request): bool
    {
        $expected = (string) config('billing.webhook_secret', '');

        if ($expected === '') {
            Log::error('billing: REVENUECAT_WEBHOOK_SECRET is not set — rejecting every webhook. Billing is inert.');

            return false;
        }

        $provided = (string) $request->header('Authorization', '');

        return hash_equals($expected, $provided);
    }
}
