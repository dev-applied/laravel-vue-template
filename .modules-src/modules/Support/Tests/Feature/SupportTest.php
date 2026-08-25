<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Modules\Support\Mail\TicketReceivedMail;
use Modules\Support\Models\SupportTicket;

beforeEach(function () {
    // The staff routes are gated on `manage-support`, registered fail-closed by
    // the module. Existing tests act as somebody permitted; the tests at the
    // bottom of this file deliberately do not.
    Gate::define('manage-support', fn () => true);

    Mail::fake();
    $this->staff = User::factory()->create();
});

function submission(array $overrides = []): array
{
    return array_merge([
        'name'    => 'Ada Lovelace', 'email' => 'Ada@Example.com',
        'subject' => 'Cannot log in', 'body' => 'It says my password is wrong.',
    ], $overrides);
}

test('anyone can submit a ticket and gets a quotable reference back', function () {
    $response = $this->postJson('/api/v1/support/tickets', submission())
        ->assertCreated()
        ->assertJsonStructure(['reference', 'message']);

    $ticket = SupportTicket::query()->sole();

    expect($ticket->email)->toBe('ada@example.com')
        ->and($ticket->reference)->toBe($response->json('reference'))
        ->and($ticket->status)->toBe(SupportTicket::STATUS_OPEN);

    Mail::assertSent(TicketReceivedMail::class);
});

test('the received email replies to the requester, not the app address', function () {
    // Otherwise hitting reply in the support mailbox answers yourself.
    $this->postJson('/api/v1/support/tickets', submission())->assertCreated();

    Mail::assertSent(TicketReceivedMail::class, fn ($mail) => $mail->hasReplyTo('ada@example.com'));
});

test('a filled honeypot is rejected', function () {
    $this->postJson('/api/v1/support/tickets', submission(['website' => 'http://spam.example']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['website']);

    expect(SupportTicket::query()->count())->toBe(0);
});

test('a link-heavy body is filed as spam without telling the sender', function () {
    $body = str_repeat('Buy now http://spam.example ', 6);

    $this->postJson('/api/v1/support/tickets', submission(['body' => $body]))
        ->assertCreated()
        ->assertJsonStructure(['reference', 'message']);   // same shape as a real one

    expect(SupportTicket::query()->sole()->is_spam)->toBeTrue();
    Mail::assertNothingSent();
});

test('submissions are validated', function () {
    $this->postJson('/api/v1/support/tickets', ['name' => '', 'email' => 'nope', 'subject' => '', 'body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'subject', 'body']);
});

test('the queue hides spam unless it is asked for', function () {
    SupportTicket::factory()->count(2)->create();
    SupportTicket::factory()->spam()->create();

    $this->actingAs($this->staff)->getJson('/api/v1/support/tickets')
        ->assertOk()->assertJsonCount(2, 'data');

    $this->actingAs($this->staff)->getJson('/api/v1/support/tickets?include_spam=1')
        ->assertOk()->assertJsonCount(3, 'data');
});

test('the queue can be filtered by status and searched by reference', function () {
    SupportTicket::factory()->create(['status' => SupportTicket::STATUS_OPEN]);
    $resolved = SupportTicket::factory()->resolved()->create();

    $this->actingAs($this->staff)->getJson('/api/v1/support/tickets?status=resolved')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($this->staff)->getJson('/api/v1/support/tickets?search='.$resolved->reference)
        ->assertOk()->assertJsonPath('data.0.id', $resolved->id);
});

test('resolving a ticket stamps when it was resolved', function () {
    $ticket = SupportTicket::factory()->create();

    $this->actingAs($this->staff)
        ->putJson("/api/v1/support/tickets/{$ticket->id}", ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('ticket.status', 'resolved');

    expect($ticket->fresh()->resolved_at)->not->toBeNull();
});

test('a ticket can be assigned and re-prioritised', function () {
    $ticket = SupportTicket::factory()->create();

    $this->actingAs($this->staff)
        ->putJson("/api/v1/support/tickets/{$ticket->id}", [
            'assigned_to' => $this->staff->id, 'priority' => 'urgent',
        ])
        ->assertOk()
        ->assertJsonPath('ticket.priority', 'urgent')
        ->assertJsonPath('ticket.assignee.id', $this->staff->id);
});

test('an unknown status is rejected', function () {
    $ticket = SupportTicket::factory()->create();

    $this->actingAs($this->staff)
        ->putJson("/api/v1/support/tickets/{$ticket->id}", ['status' => 'banana'])
        ->assertUnprocessable();
});

test('references are unique across tickets', function () {
    $references = SupportTicket::factory()->count(25)->create()->pluck('reference');

    expect($references->unique())->toHaveCount(25);
});

test('managing the queue requires authentication', function () {
    $ticket = SupportTicket::factory()->create();

    $this->getJson('/api/v1/support/tickets')->assertUnauthorized();
    $this->putJson("/api/v1/support/tickets/{$ticket->id}", ['status' => 'closed'])->assertUnauthorized();
});

test('the prune command removes only old spam', function () {
    SupportTicket::factory()->spam()->create(['created_at' => now()->subDays(60)]);
    SupportTicket::factory()->spam()->create(['created_at' => now()->subDay()]);
    SupportTicket::factory()->create(['created_at' => now()->subDays(60)]);

    $this->artisan('support:prune-spam', ['--days' => 30])->assertSuccessful();

    expect(SupportTicket::query()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Staff-only surface
//
// Every staff route carried `auth:sanctum` and nothing else. Any authenticated
// user could read the entire queue — and a support form is where customers
// paste passwords and account details as a matter of routine.
// ---------------------------------------------------------------------------

test('an ordinary user cannot read the ticket queue', function () {
    Gate::define('manage-support', fn () => false);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/support/tickets')
        ->assertForbidden();
});

test('an ordinary user cannot read one ticket', function () {
    Gate::define('manage-support', fn () => false);
    $ticket = SupportTicket::factory()->create();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson("/api/v1/support/tickets/{$ticket->id}")
        ->assertForbidden();
});

test('the queue is denied when the project has defined no gate', function () {
    Gate::define('manage-support', fn () => Gate::has('__never__'));

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/support/tickets')
        ->assertForbidden();
});

test('the public submission form still works, gate or no gate', function () {
    // The one route that must stay open. Gating it would break the feature.
    Gate::define('manage-support', fn () => false);

    $this->postJson('/api/v1/support/tickets', [
        'name'    => 'Ada',
        'email'   => 'ada@example.com',
        'subject' => 'Help',
        'body'    => 'Something is broken.',
    ])->assertCreated();
});

test('a reference collision retries instead of 500ing the public form', function () {
    // The reference was 6 characters folded onto a 36-symbol alphabet — about
    // 2.18e9 values on a `unique` column, so roughly a 1% chance of a collision
    // by 6,600 tickets. A collision threw a raw QueryException out of the
    // PUBLIC contact form: the customer saw a 500 and their message was never
    // recorded.
    $taken = SupportTicket::newReference();

    SupportTicket::factory()->create(['reference' => $taken]);

    // Force the very next generated reference to be the one already taken.
    SupportTicket::creating(function (SupportTicket $ticket) use ($taken) {
        static $once = true;

        if ($once) {
            $once              = false;
            $ticket->reference = $taken;
        }
    });

    $this->postJson('/api/v1/support/tickets', [
        'name'    => 'Ada',
        'email'   => 'ada@example.com',
        'subject' => 'Help',
        'body'    => 'Something is broken.',
    ])->assertCreated();

    expect(SupportTicket::query()->count())->toBe(2);
});

test('the reference alphabet has no look-alike characters', function () {
    // The original comment promised "uppercase without look-alike characters"
    // and delivered only the uppercasing. I, L, O, U, 0 and 1 are the ones that
    // matter when somebody reads a reference down a phone line.
    $references = collect(range(1, 50))->map(fn () => SupportTicket::newReference());

    expect($references->unique())->toHaveCount(50)
        ->and($references->every(fn (string $r) => (bool) preg_match('/^[23456789ABCDEFGHJKMNPQRSTVWXYZ]{8}$/', $r)))
        ->toBeTrue();
});
