<?php

declare(strict_types=1);

namespace Modules\Onboarding\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Onboarding\Models\OnboardingProgress;

/**
 * One user's position in the declared steps.
 *
 * Everything the API, the middleware and the frontend need is derived here so
 * the three cannot disagree — the gate blocking a request and the checklist
 * drawn for that same user are the same computation.
 */
class OnboardingState
{
    public function __construct(private readonly OnboardingRegistry $registry) {}

    /** @return array<string, mixed> */
    public function for(Authenticatable $user): array
    {
        $rows = OnboardingProgress::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->get()
            ->keyBy('step_key');

        $steps               = [];
        $outstandingRequired = 0;

        foreach ($this->registry->all() as $key => $step) {
            $row = $rows->get($key);

            // Precedence matters and is asserted in the tests: a recorded
            // completion wins, then `completedWhen`, then a recorded skip. The
            // closure is checked BEFORE the skip so a user who skipped a step
            // and then did the work elsewhere sees it as done rather than
            // passed over.
            $completed = $row?->completed_at !== null || $step->isSatisfiedBy($user);
            $skipped   = ! $completed && $row?->skipped_at !== null;

            if ($step->required && ! $completed) {
                $outstandingRequired++;
            }

            $steps[] = $step->toArray() + [
                'completed'   => $completed,
                'skipped'     => $skipped,
                'completedAt' => $row?->completed_at?->toIso8601String(),
            ];
        }

        $next = null;

        foreach ($steps as $step) {
            if (! $step['completed'] && ! $step['skipped']) {
                $next = $step['key'];

                break;
            }
        }

        return [
            'steps'               => $steps,
            'nextStep'            => $next,
            'outstandingRequired' => $outstandingRequired,
            // "Finished" means no REQUIRED step is outstanding. An optional step
            // left untouched does not hold anybody up, which is what makes it
            // optional.
            'complete'       => $outstandingRequired === 0,
            'total'          => count($steps),
            'completedCount' => count(array_filter($steps, fn (array $s) => $s['completed'])),
        ];
    }
}
