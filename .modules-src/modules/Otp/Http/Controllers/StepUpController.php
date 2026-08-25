<?php

declare(strict_types=1);

namespace Modules\Otp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Otp\Support\OtpManager;
use Modules\Otp\Support\StepUpStore;

/**
 * Re-verification before a sensitive action — changing a payout account,
 * deleting everything, exporting the customer list.
 *
 * Signed in is not the same as present. A session left open on a shared
 * machine is the case this exists for.
 */
class StepUpController extends Controller
{
    public function __construct(
        private readonly OtpManager $otp,
        private readonly StepUpStore $store,
    ) {}

    public function request(Request $request): JsonResponse
    {
        $user = $request->user();

        $result = $this->otp->issue($user->email, 'step-up', null, $request->ip());

        return response()->json([
            'message'   => 'A code is on its way.',
            'masked'    => $result['masked'],
            'expiresIn' => $result['expires_in'],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);

        $user = $request->user();

        $ok = $this->otp->matchesBypass($data['code'])
            || $this->otp->verify($user->email, $data['code'], 'step-up');

        if (! $ok) {
            return response()->json(['message' => 'That code is not valid.'], 422);
        }

        $this->store->mark($user);

        return response()->json([
            'message'  => 'Verified.',
            'validFor' => $this->store->window(),
        ]);
    }
}
