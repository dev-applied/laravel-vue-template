<?php

declare(strict_types=1);

namespace Modules\Onboarding\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Onboarding\Models\OnboardingProgress;
use Modules\Onboarding\Support\OnboardingRegistry;
use Modules\Onboarding\Support\OnboardingState;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingRegistry $registry,
        private readonly OnboardingState $state,
    ) {}

    /** The declared steps plus this user's position in them. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->state->for($request->user())]);
    }

    public function complete(Request $request, string $step): JsonResponse
    {
        abort_unless($this->registry->has($step), 404);

        // A step with a `completedWhen` reports itself, so accepting a POST for
        // it is a bypass: "verify your email" would be marked done by clicking
        // a button, which is the one thing it must not be. Noticed by loading
        // the page rather than by a test — the checklist rendered a "Mark done"
        // button beside an email-verification step.
        abort_if(
            $this->registry->get($step)->isAutoDetected(),
            422,
            'That step completes itself once the work is done — it cannot be ticked off by hand.',
        );

        OnboardingProgress::markCompleted($request->user()->getKey(), $step);

        return response()->json(['data' => $this->state->for($request->user())]);
    }

    public function skip(Request $request, string $step): JsonResponse
    {
        abort_unless($this->registry->has($step), 404);

        // A REQUIRED step cannot be skipped. Allowing it would make "required"
        // a label rather than a rule, and the gate would then pass users who
        // clicked past the thing the gate exists to insist on.
        abort_if($this->registry->get($step)->required, 422, 'That step is required and cannot be skipped.');

        OnboardingProgress::markSkipped($request->user()->getKey(), $step);

        return response()->json(['data' => $this->state->for($request->user())]);
    }

    /** Skip everything skippable — the "I'll do this later" button. */
    public function skipAll(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        foreach ($this->registry->all() as $key => $step) {
            if (! $step->required) {
                OnboardingProgress::markSkipped($userId, $key);
            }
        }

        return response()->json(['data' => $this->state->for($request->user())]);
    }
}
