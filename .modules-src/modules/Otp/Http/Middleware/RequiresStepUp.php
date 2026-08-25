<?php

declare(strict_types=1);

namespace Modules\Otp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Otp\Support\StepUpStore;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route behind a recent step-up.
 *
 *   Route::delete('/account', ...)->middleware('otp.step-up');
 */
class RequiresStepUp
{
    public function __construct(private readonly StepUpStore $store) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->store->isVerified($user)) {
            // 428, not 401 — a bare 401 makes the frontend log the person out
            // instead of opening the step-up dialog.
            return response()->json([
                'message' => 'Please confirm it is you.',
                'stepUp'  => true,
            ], 428);
        }

        return $next($request);
    }
}
