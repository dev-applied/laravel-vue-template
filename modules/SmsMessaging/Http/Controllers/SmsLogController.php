<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SmsMessaging\Models\SmsMessage;
use Modules\SmsMessaging\Models\SmsOptOut;

/**
 * Support's view of what was sent and who has opted out.
 *
 * Gated on `view-sms-log`, which falls CLOSED when a project has not defined
 * it. The log holds message bodies and phone numbers — deciding to expose that
 * has to be an act, not a default, and "any signed-in user" is not a boundary.
 */
class SmsLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeLog($request);

        $messages = SmsMessage::query()
            ->when($request->filled('phone_number'), fn ($q) => $q->where('phone_number', 'like', '%'.$request->string('phone_number').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return response()->json($messages);
    }

    public function optOuts(Request $request): JsonResponse
    {
        $this->authorizeLog($request);

        return response()->json(
            SmsOptOut::query()->latest('id')->paginate(min((int) $request->integer('per_page', 25), 100))
        );
    }

    /**
     * Remove somebody from the opt-out list by hand.
     *
     * Exists because a genuine "please put me back on" arrives by phone or
     * email as often as by text, and the alternative is somebody editing the
     * table directly. Not exposed in the UI for the same reason it is gated:
     * re-subscribing a number on the user's behalf is a decision, and the
     * request for it happens outside this system.
     */
    public function removeOptOut(Request $request, string $number): JsonResponse
    {
        $this->authorizeLog($request);

        SmsOptOut::remove($number);

        return response()->json(status: 204);
    }

    private function authorizeLog(Request $request): void
    {
        abort_unless(
            $request->user()?->can('view-sms-log') === true,
            403,
            'You do not have permission to read the SMS log.',
        );
    }
}
