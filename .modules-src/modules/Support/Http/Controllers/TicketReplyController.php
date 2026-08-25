<?php

declare(strict_types=1);

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Modules\Support\Http\Resources\TicketReplyResource;
use Modules\Support\Mail\TicketReplyMail;
use Modules\Support\Models\SupportTicket;

/** Ticketing variant only — dropped when installed with mode=contact. */
class TicketReplyController extends Controller
{
    public function store(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'body'        => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $reply = $ticket->replies()->create([
            'user_id'     => $request->user()?->getKey(),
            'body'        => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
        ]);

        // An internal note is staff-only. Emailing it to the requester is the
        // single worst bug this feature can have, so the guard is explicit.
        if (! $reply->is_internal) {
            Mail::to($ticket->email)->send(new TicketReplyMail($ticket, $reply));

            $ticket->update(['status' => SupportTicket::STATUS_PENDING]);
        }

        return response()->json(['reply' => new TicketReplyResource($reply->load('author'))], 201);
    }
}
