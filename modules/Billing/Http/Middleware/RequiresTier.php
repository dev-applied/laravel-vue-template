<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Billing\Enums\SubscriptionTier;
use Modules\Billing\Support\Entitlements;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side enforcement.
 *
 *   Route::post('/reports/export', ...)->middleware('tier:premium');
 *
 * Client-side gating decides what to SHOW. A gate that exists only in the
 * client is a suggestion.
 */
class RequiresTier
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next, string $tier = 'basic'): Response
    {
        $user = $request->user();

        $required = SubscriptionTier::tryFrom($tier) ?? SubscriptionTier::Basic;

        if ($user === null || ! $this->entitlements->hasTier($user, $required)) {
            // 402, so the frontend can open the paywall rather than treating it
            // as a permissions problem or logging the person out.
            return response()->json([
                'message'      => 'This needs a subscription.',
                'requiredTier' => $required->value,
                'upgrade'      => true,
            ], 402);
        }

        return $next($request);
    }
}
