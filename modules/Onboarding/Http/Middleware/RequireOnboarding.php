<?php

declare(strict_types=1);

namespace Modules\Onboarding\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Onboarding\Support\OnboardingState;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request while the user still owes a REQUIRED step.
 *
 * Applied per-route by the project, never globally by this module:
 *
 *   Route::middleware(['auth:sanctum', 'onboarded'])->group(...);
 *
 * Global registration is the trap. The onboarding endpoints themselves, the
 * auth endpoints and whatever screens the steps link to all have to stay
 * reachable, so a global gate has to carve out exceptions it cannot know — and
 * the failure is a signed-in user who can reach nothing at all, including the
 * page that would let them finish.
 *
 * 403 with a machine-readable body rather than a redirect: this is an API, and
 * the SPA router decides where to send someone.
 */
class RequireOnboarding
{
    public function __construct(private readonly OnboardingState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $state = $this->state->for($user);

        if ($state['complete']) {
            return $next($request);
        }

        return response()->json([
            'message'    => 'Finish setting up your account first.',
            'onboarding' => [
                'complete'            => false,
                'nextStep'            => $state['nextStep'],
                'outstandingRequired' => $state['outstandingRequired'],
            ],
        ], 403);
    }
}
