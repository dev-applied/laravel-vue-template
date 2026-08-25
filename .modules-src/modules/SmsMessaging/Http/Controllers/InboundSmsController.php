<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\SmsMessaging\Models\SmsOptOut;
use Modules\SmsMessaging\Support\PhoneNumber;

/**
 * The vendor's inbound webhook. Handles the keywords carriers require.
 *
 * STOP / START / HELP are not a product decision. US carriers and the CTIA
 * require them of any programme that sends A2P messages, and a vendor will
 * suspend a sender that ignores them. Twilio answers some keywords itself
 * depending on account settings, but relying on that means the application's
 * own opt-out list never learns about it — so the number is suppressed at the
 * vendor and still "sendable" as far as the app is concerned, which is exactly
 * the state that produces a violation the day the vendor changes.
 *
 * Replies in TwiML, which is what Twilio expects. A vendor with a different
 * response format needs its own route, not a flag here.
 */
class InboundSmsController extends Controller
{
    /** Case-insensitive; carriers strip punctuation and whitespace before delivery. */
    private const STOP = ['stop', 'stopall', 'unsubscribe', 'cancel', 'end', 'quit'];

    private const START = ['start', 'unstop', 'yes'];

    private const HELP = ['help', 'info'];

    public function __invoke(Request $request): Response
    {
        $from    = (string) $request->input('From', '');
        $keyword = mb_strtolower(mb_trim((string) $request->input('Body', '')));
        $keyword = preg_replace('/[^a-z]/', '', $keyword) ?? '';

        $number = PhoneNumber::normalise($from);

        if ($number === null) {
            // Answer 200 with nothing. A 4xx makes the vendor retry a webhook
            // that will never succeed, and some vendors disable an endpoint
            // that keeps failing.
            return $this->twiml(null);
        }

        if (in_array($keyword, self::STOP, true)) {
            SmsOptOut::add($number, "Replied '{$keyword}'");

            // No confirmation reply: the carrier sends its own, and answering
            // as well is both a duplicate and a message to somebody who has
            // just said stop sending messages.
            return $this->twiml(null);
        }

        if (in_array($keyword, self::START, true)) {
            SmsOptOut::remove($number);

            return $this->twiml(config('sms.start_reply', 'You are re-subscribed. Reply STOP to opt out.'));
        }

        if (in_array($keyword, self::HELP, true)) {
            return $this->twiml(config('sms.help_reply', 'Reply STOP to opt out.'));
        }

        return $this->twiml(null);
    }

    private function twiml(?string $message): Response
    {
        $body = $message === null
            ? '<?xml version="1.0" encoding="UTF-8"?><Response/>'
            : '<?xml version="1.0" encoding="UTF-8"?><Response><Message>'
                .htmlspecialchars($message, ENT_XML1).'</Message></Response>';

        return response($body, 200, ['Content-Type' => 'text/xml']);
    }
}
