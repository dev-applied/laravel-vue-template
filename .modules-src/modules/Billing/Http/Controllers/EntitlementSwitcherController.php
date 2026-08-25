<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\PaymentProvider;
use Modules\Billing\Enums\SubscriptionPlan;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Enums\SubscriptionTier;
use Modules\Billing\Services\EntitlementWriter;

/**
 * The QA affordance: flip your own entitlement to any state.
 *
 * Reaching "trial expired, card declined, previously premium" through real
 * store purchases is impractical, and a project without this grows a worse
 * workaround — a tester given production admin, or a hardcoded branch that
 * ships.
 *
 * It is a deliberate hole in a control, so it follows the same rules as the
 * sandbox flag:
 *   - env-gated, and OFF unless explicitly switched on
 *   - refuses outright in production, whatever the env says
 *   - acts only on the caller's OWN entitlement — no privilege over others
 *   - logs every use, loudly
 */
class EntitlementSwitcherController extends Controller
{
    public function __construct(private readonly EntitlementWriter $writer) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Both conditions. The env flag alone is one bad deploy away from
        // being a self-serve upgrade button in production.
        if (! config('billing.allow_switcher', false) || app()->environment('production')) {
            throw new AppException('Not available.', 404);
        }

        $data = $request->validate([
            'tier'       => ['required', Rule::enum(SubscriptionTier::class)],
            'status'     => ['required', Rule::enum(SubscriptionStatus::class)],
            'plan'       => ['sometimes', Rule::enum(SubscriptionPlan::class)],
            'trial_used' => ['sometimes', 'boolean'],
            'days_left'  => ['sometimes', 'integer', 'min:-3650', 'max:3650'],
        ]);

        $user = $request->user();

        Log::warning('billing: QA entitlement switcher used', [
            'user_id' => $user->getKey(),
            'to'      => $data,
        ]);

        $patch = [
            'tier'   => $data['tier'],
            'status' => $data['status'],
            'plan'   => $data['plan'] ?? SubscriptionPlan::None->value,
            // Marked manual so nothing downstream mistakes a switched state
            // for a real purchase, and so management routing does not offer a
            // store link that does not exist.
            'provider'             => PaymentProvider::Manual->value,
            'cancel_at_period_end' => false,
        ];

        if (array_key_exists('trial_used', $data)) {
            $patch['trial_used'] = $data['trial_used'];
        }

        if (array_key_exists('days_left', $data)) {
            $patch['current_period_end'] = now()->addDays($data['days_left'])->getTimestampMs();
        }

        $this->writer->apply((string) $user->getKey(), $patch);

        return response()->json(['message' => 'Entitlement set.', 'entitlement' => $patch]);
    }
}
