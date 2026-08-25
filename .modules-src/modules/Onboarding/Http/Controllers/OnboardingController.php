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
