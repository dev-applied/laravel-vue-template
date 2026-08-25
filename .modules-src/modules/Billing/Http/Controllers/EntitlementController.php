<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Billing\Models\UserEntitlement;
use Modules\Billing\Support\Entitlements;

/**
 * What the client reads. It never writes.
 */
class EntitlementController extends Controller
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function show(Request $request): JsonResponse
    {
        /** @var UserEntitlement $entitlement */
        $entitlement = $this->entitlements->for($request->user());

        $provider = $entitlement->provider;

        return response()->json([
            'tier'              => $entitlement->tier->value,
            'status'            => $entitlement->status->value,
            'plan'              => $entitlement->plan->value,
            'provider'          => $provider->value,
            'isActive'          => $entitlement->isActive(),
            'cancelAtPeriodEnd' => $entitlement->cancel_at_period_end,
            'currentPeriodEnd'  => $entitlement->current_period_end?->toIso8601String(),
            'trialEndsAt'       => $entitlement->trial_ends_at?->toIso8601String(),
            'trialUsed'         => $entitlement->trial_used,
            // "Never subscribed" and "trial expired" both resolve to free and
            // need different copy.
            'isFirstTime' => $entitlement->isFirstTime(),
            // Follows the PROCESSOR, not the device. Null for Apple viewed on
            // the web is deliberate — linking an iOS subscriber to web billing
            // is external-purchase steering and gets the app rejected, so the
            // UI shows copy instead.
            'managementUrl' => $provider->managementUrl(),
        ]);
    }
}
