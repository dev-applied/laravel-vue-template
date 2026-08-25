<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormSubmission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Gate::define('manage-forms', fn () => true);
});

test('a form is created with a slug', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/forms', [
            'name'   => 'Volunteer Intake',
            'schema' => [['key' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'required' => true]],
        ])
        ->assertCreated()
        ->assertJsonPath('slug', 'volunteer-intake');
});

test('two forms with the same name get distinct slugs', function () {
    Form::factory()->create(['name' => 'Intake', 'slug' => 'intake']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/forms', [
            'name'   => 'Intake',
            'schema' => [['key' => 'a', 'label' => 'A', 'type' => 'text']],
        ])
        ->assertCreated()
        ->assertJsonPath('slug', 'intake-2');
});

test('a schema with no fields is refused', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/forms', ['name' => 'Empty', 'schema' => []])
        ->assertStatus(422);
});

test('an unknown field type is refused', function () {
    // The renderer has no component for it, so it would render nothing — a
    // required field nobody can see and a form that can never be submitted.
    $this->actingAs($this->user)
        ->postJson('/api/v1/forms', [
            'name'   => 'Bad',
            'schema' => [['key' => 'x', 'label' => 'X', 'type' => 'hologram']],
        ])
        ->assertStatus(422);
});

test('a choice field with no options is refused', function () {
    // A required dropdown with nothing in it can never be satisfied, and the
    // form looks fine.
    $this->actingAs($this->user)
        ->postJson('/api/v1/forms', [
            'name'   => 'Bad',
            'schema' => [['key' => 'x', 'label' => 'Pick', 'type' => 'select', 'options' => []]],
        ])
        ->assertStatus(422);
});

test('a duplicate key is refused', function () {
    // One answer would silently overwrite the other.
    $this->actingAs($this->user)
        ->postJson('/api/v1/forms', [
            'name'   => 'Bad',
            'schema' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['key' => 'email', 'label' => 'Work email', 'type' => 'email'],
            ],
        ])
        ->assertStatus(422);
});

test('a generated key never collides with an explicit one', function () {
    // Even when the explicit one comes later in the list.
    $this->actingAs($this->user)
        ->postJson('/api/v1/forms', [
            'name'   => 'Mixed',
            'schema' => [
                ['label' => 'Email', 'type' => 'email'],
                ['key' => 'email', 'label' => 'Work email', 'type' => 'email'],
            ],
        ])
        ->assertCreated();

    $keys = array_column(Form::latest('id')->first()->schema, 'key');

    expect($keys)->toBe(['email_2', 'email']);
});

test('missing keys are generated from labels and deduplicated', function () {
    // A builder UI generating keys from labels produces "email" twice the
    // moment someone adds a second "Email" field.
    $this->actingAs($this->user)
        ->postJson('/api/v1/forms', [
            'name'   => 'Generated',
            'schema' => [
                ['label' => 'Email', 'type' => 'email'],
                ['label' => 'Email', 'type' => 'email'],
            ],
        ])
        ->assertCreated();

    $keys = array_column(Form::latest('id')->first()->schema, 'key');

    expect($keys)->toBe(['email', 'email_2']);
});

test('a published public form renders without an account', function () {
    $form = Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->getJson('/api/v1/forms/intake/render')
        ->assertOk()
        ->assertJsonPath('name', $form->name)
        ->assertJsonCount(2, 'schema');
});

test('the render payload does not expose the whole model', function () {
    // A draft's internal fields and its author are nobody else's business.
    Form::factory()->publicForm()->create(['slug' => 'intake']);

    $response = $this->getJson('/api/v1/forms/intake/render')->assertOk();

    expect($response->json())->not->toHaveKey('created_by')
        ->and($response->json())->not->toHaveKey('is_published');
});

test('an unpublished form is a 404, not a 403', function () {
    // Whether a draft exists at a guessable slug is not public information.
    Form::factory()->draft()->publicForm()->create(['slug' => 'secret']);

    $this->getJson('/api/v1/forms/secret/render')->assertNotFound();
});

test('a closed form stops accepting submissions', function () {
    Form::factory()->publicForm()->closed()->create(['slug' => 'late']);

    $this->postJson('/api/v1/forms/late/submit', ['answers' => []])->assertNotFound();
});

test('a non-public form requires signing in', function () {
    Form::factory()->create(['slug' => 'internal']);

    // 404, not 401. See the enumeration test below for why.
    $this->getJson('/api/v1/forms/internal/render')->assertNotFound();

    $this->actingAs($this->user)->getJson('/api/v1/forms/internal/render')->assertOk();
});

test('an anonymous caller cannot tell a private form from one that does not exist', function () {
    // A 404 for "no such form" beside a 401 for "private, sign in" let anyone
    // enumerate internal form slugs by status code alone — and the slug is the
    // leak: nobody needs the contents of `exec-comp-review-2026` to learn
    // something from knowing it exists.
    Form::factory()->create(['slug' => 'exec-comp-review-2026']);

    $private = $this->getJson('/api/v1/forms/exec-comp-review-2026/render')->assertNotFound();
    $absent  = $this->getJson('/api/v1/forms/no-such-form-at-all/render')->assertNotFound();

    // Identical down to the message, or the body becomes the oracle instead.
    expect($private->json('message'))->toBe($absent->json('message'));
});

test('the refusal still tells a real visitor what to do', function () {
    // The cost of collapsing the two is that someone arriving signed-out on a
    // legitimate private link is told "not available". The message carries the
    // sign-in hint so they are not stuck — and it costs the prober nothing,
    // because they receive exactly the same sentence.
    Form::factory()->create(['slug' => 'internal']);

    expect($this->getJson('/api/v1/forms/internal/render')->json('message'))
        ->toContain('sign in');
});

test('a valid submission is stored', function () {
    Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->postJson('/api/v1/forms/intake/submit', [
        'answers' => ['full_name' => 'Jane Doe', 'email' => 'jane@example.com'],
    ])->assertCreated();

    expect(FormSubmission::count())->toBe(1)
        ->and(FormSubmission::first()->answers['full_name'])->toBe('Jane Doe');
});

test('a required field is enforced from the server copy of the schema', function () {
    Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->postJson('/api/v1/forms/intake/submit', ['answers' => ['email' => 'jane@example.com']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answers.full_name');
});

test('a client-supplied schema is ignored', function () {
    // Trusting one would let anyone drop the `required` off a field, or invent
    // a field that was never on the form.
    Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->postJson('/api/v1/forms/intake/submit', [
        'schema'  => [['key' => 'whatever', 'label' => 'Whatever', 'type' => 'text']],
        'answers' => ['whatever' => 'x'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('answers.full_name');
});

test('undeclared answers are discarded, not stored', function () {
    // A submissions export is read by people; unexpected keys are how junk
    // gets in front of them.
    Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->postJson('/api/v1/forms/intake/submit', [
        'answers' => ['full_name' => 'Jane', 'email' => 'jane@example.com', 'injected' => '<script>'],
    ])->assertCreated();

    expect(FormSubmission::first()->answers)->not->toHaveKey('injected');
});

test('a field type is validated', function () {
    Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->postJson('/api/v1/forms/intake/submit', [
        'answers' => ['full_name' => 'Jane', 'email' => 'not-an-email'],
    ])->assertStatus(422)->assertJsonValidationErrors('answers.email');
});

test('a choice field only accepts its declared choices', function () {
    // Without this the dropdown is a free-text field and the options are a
    // suggestion.
    Form::factory()->publicForm()->create([
        'slug'   => 'pick',
        'schema' => [[
            'key'     => 'size', 'label' => 'Size', 'type' => 'select', 'required' => true,
            'options' => [['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']],
        ]],
    ]);

    $this->postJson('/api/v1/forms/pick/submit', ['answers' => ['size' => 'xxl']])
        ->assertStatus(422);

    $this->postJson('/api/v1/forms/pick/submit', ['answers' => ['size' => 'l']])
        ->assertCreated();
});

test('the schema is snapshotted at submit time', function () {
    // Editing a form afterwards must not rewrite the meaning of answers
    // already collected — the worst failure mode a form builder has.
    $form = Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->postJson('/api/v1/forms/intake/submit', [
        'answers' => ['full_name' => 'Jane', 'email' => 'jane@example.com'],
    ])->assertCreated();

    $this->actingAs($this->user)->putJson("/api/v1/forms/{$form->id}", [
        'schema' => [['key' => 'full_name', 'label' => 'Legal name for the contract', 'type' => 'text']],
    ])->assertOk();

    $labelled = FormSubmission::first()->labelled();

    expect($labelled[0]['label'])->toBe('Full name')
        ->and($labelled[0]['value'])->toBe('Jane');
});

test('a signed-in submission records who sent it', function () {
    Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->actingAs($this->user)->postJson('/api/v1/forms/intake/submit', [
        'answers' => ['full_name' => 'Jane', 'email' => 'jane@example.com'],
    ])->assertCreated();

    expect(FormSubmission::first()->user_id)->toBe($this->user->id);
});

test('an anonymous submission is allowed on a public form', function () {
    Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->postJson('/api/v1/forms/intake/submit', [
        'answers' => ['full_name' => 'Jane', 'email' => 'jane@example.com'],
    ])->assertCreated();

    expect(FormSubmission::first()->user_id)->toBeNull();
});

test('submissions come back labelled', function () {
    $form = Form::factory()->publicForm()->create(['slug' => 'intake']);

    $this->postJson('/api/v1/forms/intake/submit', [
        'answers' => ['full_name' => 'Jane', 'email' => 'jane@example.com'],
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/forms/{$form->id}/submissions")
        ->assertOk()
        ->assertJsonPath('data.0.answers.0.label', 'Full name')
        ->assertJsonPath('data.0.answers.0.value', 'Jane');
});

test('reading submissions is gated', function () {
    Gate::define('manage-forms', fn () => false);
    $form = Form::factory()->create();

    $this->actingAs($this->user)->getJson("/api/v1/forms/{$form->id}/submissions")->assertForbidden();
});

test('authoring is gated', function () {
    Gate::define('manage-forms', fn () => false);

    $this->actingAs($this->user)->getJson('/api/v1/forms')->assertForbidden();
    $this->actingAs($this->user)->postJson('/api/v1/forms', [])->assertForbidden();
});

test('deleting a form takes its submissions', function () {
    $form = Form::factory()->publicForm()->create(['slug' => 'intake']);
    $this->postJson('/api/v1/forms/intake/submit', ['answers' => ['full_name' => 'J', 'email' => 'j@e.com']]);

    $this->actingAs($this->user)->deleteJson("/api/v1/forms/{$form->id}")->assertOk();

    expect(FormSubmission::count())->toBe(0);
});

test('a checkbox answer stays a boolean', function () {
    Form::factory()->publicForm()->create([
        'slug'   => 'consent',
        'schema' => [['key' => 'agreed', 'label' => 'I agree', 'type' => 'checkbox', 'required' => true]],
    ]);

    $this->postJson('/api/v1/forms/consent/submit', ['answers' => ['agreed' => true]])
        ->assertCreated();

    expect(FormSubmission::first()->answers['agreed'])->toBeTrue();
});

test('an unanswered optional field is stored as null', function () {
    Form::factory()->publicForm()->create([
        'slug'   => 'optional',
        'schema' => [
            ['key' => 'a', 'label' => 'A', 'type' => 'text', 'required' => true],
            ['key' => 'b', 'label' => 'B', 'type' => 'text'],
        ],
    ]);

    $this->postJson('/api/v1/forms/optional/submit', ['answers' => ['a' => 'x']])->assertCreated();

    expect(FormSubmission::first()->answers)->toHaveKey('b')
        ->and(FormSubmission::first()->answers['b'])->toBeNull();
});
