<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Modules\Support\Mail\TicketReplyMail;
use Modules\Support\Models\SupportTicket;

// Ticketing variant only — dropped when installed with mode=contact.

beforeEach(function () {
    // Staff routes are gated on `manage-support` (fail-closed in the module).
    Gate::define('manage-support', fn () => true);

    Mail::fake();
    $this->staff  = User::factory()->create();
    $this->ticket = SupportTicket::factory()->create(['email' => 'ada@example.com']);
});

test('a public reply is emailed to the requester and moves the ticket to pending', function () {
    $this->actingAs($this->staff)
        ->postJson("/api/v1/support/tickets/{$this->ticket->id}/replies", ['body' => 'Try a reset.'])
        ->assertCreated()
        ->assertJsonPath('reply.isInternal', false);

    Mail::assertSent(TicketReplyMail::class, fn ($mail) => $mail->hasTo('ada@example.com'));
    expect($this->ticket->fresh()->status)->toBe(SupportTicket::STATUS_PENDING);
});

test('an internal note is never emailed to the requester', function () {
    // The single worst bug this feature can have.
    $this->actingAs($this->staff)
        ->postJson("/api/v1/support/tickets/{$this->ticket->id}/replies", [
            'body' => 'Customer has form a dozen times, deprioritise.', 'is_internal' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('reply.isInternal', true);

    Mail::assertNothingSent();
    expect($this->ticket->fresh()->status)->toBe(SupportTicket::STATUS_OPEN);
});

test('replies come back with the ticket in order', function () {
    $this->actingAs($this->staff)
        ->postJson("/api/v1/support/tickets/{$this->ticket->id}/replies", ['body' => 'First'])
        ->assertCreated();
    $this->actingAs($this->staff)
        ->postJson("/api/v1/support/tickets/{$this->ticket->id}/replies", ['body' => 'Second'])
        ->assertCreated();

    $this->actingAs($this->staff)
        ->getJson("/api/v1/support/tickets/{$this->ticket->id}")
        ->assertOk()
        ->assertJsonPath('ticket.replies.0.body', 'First')
        ->assertJsonPath('ticket.replies.1.body', 'Second');
});

test('a reply body is required', function () {
    $this->actingAs($this->staff)
        ->postJson("/api/v1/support/tickets/{$this->ticket->id}/replies", ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

test('replying requires authentication', function () {
    $this->postJson("/api/v1/support/tickets/{$this->ticket->id}/replies", ['body' => 'hi'])
        ->assertUnauthorized();
});

test('an ordinary user cannot use the reply endpoint as a mail relay', function () {
    // The sharpest edge in this module. `replies.store` mails an arbitrary
    // 5000-character body to the ticket's email address, from our domain, with
    // subject "Re: [REF] <their own subject>" — into a thread the customer
    // already trusts. Ungated, that is an authenticated phishing relay with our
    // sending reputation behind it.
    Gate::define('manage-support', fn () => false);
    Mail::fake();

    $ticket = SupportTicket::factory()->create(['email' => 'victim@example.com']);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/v1/support/tickets/{$ticket->id}/replies", [
            'body' => 'Please confirm your password at https://not-us.example.com',
        ])
        ->assertForbidden();

    Mail::assertNothingSent();
});
