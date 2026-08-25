<?php

declare(strict_types=1);

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Modules\Support\Http\Requests\StoreTicketRequest;
use Modules\Support\Http\Resources\TicketResource;
use Modules\Support\Mail\TicketReceivedMail;
use Modules\Support\Models\SupportTicket;

class TicketController extends Controller
{
    /**
     * Public. Anyone can submit, so this is the endpoint that gets abused —
     * hence the honeypot in the FormRequest, the throttle on the route, and
     * a link-count heuristic that files obvious spam without bouncing it
     * (a bot told it failed simply tries again differently).
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        // createWithReference, not create: a reference collision on the PUBLIC
        // form must be a retry, not a 500 with the customer's message lost.
        $ticket = SupportTicket::createWithReference([
            'user_id'    => $request->user()?->getKey(),
            'name'       => $request->string('name')->toString(),
            'email'      => mb_strtolower($request->string('email')->toString()),
            'subject'    => $request->string('subject')->toString(),
            'body'       => $request->string('body')->toString(),
            'ip_address' => $request->ip(),
            'is_spam'    => $this->looksLikeSpam($request->string('body')->toString()),
        ]);

        if (! $ticket->is_spam && ($mailbox = config('mail.support_address', config('mail.from.address')))) {
            Mail::to($mailbox)->send(new TicketReceivedMail($ticket));
        }

        // Identical response either way — never tell a bot it was filtered.
        return response()->json([
            'reference' => $ticket->reference,
            'message'   => 'Thanks — we have received your message.',
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->with('assignee')
            ->filter($request->only(['status', 'priority', 'assigned_to', 'search', 'include_spam']))
            ->latest('id')
            ->vuetifyPaginate();

        $tickets->setCollection(
            $tickets->getCollection()->map(fn (SupportTicket $t) => new TicketResource($t))->collect()
        );

        return response()->json($tickets);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        $ticket->load(['assignee', 'replies.author']);

        return response()->json(['ticket' => new TicketResource($ticket)]);
    }

    public function update(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'status'      => ['sometimes', 'in:'.implode(',', SupportTicket::STATUSES)],
            'priority'    => ['sometimes', 'in:'.implode(',', SupportTicket::PRIORITIES)],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'is_spam'     => ['sometimes', 'boolean'],
        ]);

        if (($data['status'] ?? null) === SupportTicket::STATUS_RESOLVED && $ticket->resolved_at === null) {
            $data['resolved_at'] = now();
        }

        $ticket->update($data);

        return response()->json(['ticket' => new TicketResource($ticket->fresh()->load('assignee'))]);
    }

    public function destroy(SupportTicket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json()->setStatusCode(204);
    }

    /**
     * Deliberately crude and conservative: a link-heavy body is the one signal
     * that is both cheap and hard for a form bot to avoid. It FLAGS rather than
     * rejects, so a false positive is a row in the spam filter, not a customer
     * who could not reach you.
     */
    private function looksLikeSpam(string $body): bool
    {
        return preg_match_all('#https?://#i', $body) >= 5;
    }
}
