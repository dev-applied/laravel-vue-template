<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Checklists\Models\Checklist;
use Modules\Checklists\Models\ChecklistResponse;
use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Checklists\Support\ChecklistSubjects;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Registered against User — the only model the kernel contract guarantees
    // a module can rely on.
    app(ChecklistSubjects::class)->register('user', User::class);

    $this->template = ChecklistTemplate::query()->create(['name' => 'Pre-delivery inspection']);
    $this->template->items()->createMany([
        ['label' => 'Tyres', 'is_required' => true, 'position' => 0],
        ['label' => 'Paintwork', 'is_required' => true, 'requires_evidence' => true, 'position' => 1],
        ['label' => 'Spare wheel', 'is_required' => false, 'position' => 2],
    ]);
});

test('an instance copies the template items rather than referencing them', function () {
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);

    expect($checklist->responses)->toHaveCount(3)
        ->and($checklist->responses[0]->label)->toBe('Tyres')
        ->and($checklist->responses[0]->status)->toBe(ChecklistResponse::STATUS_PENDING);

    // The template changes afterwards; the instance must not.
    $this->template->items()->where('label', 'Tyres')->update(['label' => 'Tyres and wheels']);
    $this->template->items()->create(['label' => 'Added later', 'position' => 9]);

    $checklist->refresh()->load('responses');

    expect($checklist->responses)->toHaveCount(3, 'a later template item must not appear on an old instance')
        ->and($checklist->responses[0]->label)->toBe('Tyres', 'a reworded template item must not rewrite history');
});

test('the instance keeps its name after the template is deleted', function () {
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);

    $this->template->delete();

    $checklist->refresh();

    expect($checklist->name)->toBe('Pre-delivery inspection')
        ->and($checklist->checklist_template_id)->toBeNull()
        ->and($checklist->responses()->count())->toBe(3, 'deleting a template must not delete the inspections done under it');
});

test('a checklist cannot be completed while a required item is unanswered', function () {
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/checklists/{$checklist->id}/complete")
        ->assertStatus(422);

    // EVERY reason, not the first. A checklist that reveals one missing item per
    // attempt is the reason people stop using them.
    expect($response->json('outstanding'))->toHaveCount(2)
        ->and(implode(' ', $response->json('outstanding')))->toContain('Tyres')
        ->and(implode(' ', $response->json('outstanding')))->toContain('Paintwork');
});

test('an item that requires evidence blocks completion until it has some', function () {
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);

    foreach ($checklist->responses as $response) {
        $this->actingAs($this->user)
            ->patchJson("/api/v1/checklists/{$checklist->id}/responses/{$response->id}", ['status' => 'pass'])
            ->assertOk();
    }

    $body = $this->actingAs($this->user)
        ->postJson("/api/v1/checklists/{$checklist->id}/complete")
        ->assertStatus(422)
        ->json('outstanding');

    expect(implode(' ', $body))->toContain('Paintwork')
        ->and(implode(' ', $body))->toContain('photo');
});

test('evidence is demanded of the ANSWER, not the item', function () {
    // A step marked not-applicable cannot have a photo of itself, and demanding
    // one is how a checklist becomes unfinishable.
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);

    $paintwork = $checklist->responses->firstWhere('label', 'Paintwork');

    $this->actingAs($this->user)
        ->patchJson("/api/v1/checklists/{$checklist->id}/responses/{$paintwork->id}", ['status' => 'na'])
        ->assertOk();

    foreach ($checklist->responses->where('label', '!=', 'Paintwork') as $response) {
        $this->actingAs($this->user)
            ->patchJson("/api/v1/checklists/{$checklist->id}/responses/{$response->id}", ['status' => 'pass'])
            ->assertOk();
    }

    $this->actingAs($this->user)->postJson("/api/v1/checklists/{$checklist->id}/complete")->assertOk();
});

test('an optional item left pending does not block completion', function () {
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);

    foreach ($checklist->responses->where('is_required', true) as $response) {
        $payload = ['status' => $response->requires_evidence ? 'fail' : 'pass'];

        $this->actingAs($this->user)
            ->patchJson("/api/v1/checklists/{$checklist->id}/responses/{$response->id}", $payload)
            ->assertOk();
    }

    $this->actingAs($this->user)->postJson("/api/v1/checklists/{$checklist->id}/complete")->assertOk();

    expect($checklist->fresh()->status)->toBe(Checklist::STATUS_COMPLETE);
});

test('a completed checklist cannot be edited', function () {
    // It is a record. Editing after sign-off means the signature no longer
    // describes what it signed.
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);

    foreach ($checklist->responses as $response) {
        $this->actingAs($this->user)
            ->patchJson("/api/v1/checklists/{$checklist->id}/responses/{$response->id}", ['status' => 'fail'])
            ->assertOk();
    }

    $this->actingAs($this->user)->postJson("/api/v1/checklists/{$checklist->id}/complete")->assertOk();

    $first = $checklist->responses->first();

    $this->actingAs($this->user)
        ->patchJson("/api/v1/checklists/{$checklist->id}/responses/{$first->id}", ['status' => 'pass'])
        ->assertStatus(422);

    expect($first->fresh()->status)->toBe('fail');
});

test('pending is not an answer a caller may set', function () {
    // It is the ABSENCE of an answer. Accepting it would quietly un-answer a
    // line somebody had already signed off.
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);
    $response  = $checklist->responses->first();

    $this->actingAs($this->user)
        ->patchJson("/api/v1/checklists/{$checklist->id}/responses/{$response->id}", ['status' => 'pending'])
        ->assertStatus(422);
});

test('a response belonging to another checklist is a 404', function () {
    $mine   = Checklist::instantiate($this->template->fresh('items'), $this->user);
    $theirs = Checklist::instantiate($this->template->fresh('items'), User::factory()->create());

    $this->actingAs($this->user)
        ->patchJson("/api/v1/checklists/{$mine->id}/responses/{$theirs->responses->first()->id}", ['status' => 'pass'])
        ->assertNotFound();
});

test('a subject type that is not registered is refused', function () {
    // The endpoint takes a subject type off the wire. Without the allow-list
    // this is an arbitrary-model-lookup endpoint.
    $this->actingAs($this->user)
        ->postJson('/api/v1/checklists', [
            'template_id'  => $this->template->id,
            'subject_type' => 'App\\Models\\User',
            'subject_id'   => $this->user->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('subject_type');
});

test('an archived template cannot be started', function () {
    $this->template->forceFill(['is_active' => false])->save();

    $this->actingAs($this->user)
        ->postJson('/api/v1/checklists', [
            'template_id'  => $this->template->id,
            'subject_type' => 'user',
            'subject_id'   => $this->user->id,
        ])
        ->assertStatus(422);
});

test('editing templates is gated but filling one in is not', function () {
    // Who may change what gets inspected is a different question from who
    // carries out the inspection.
    $this->actingAs($this->user)
        ->postJson('/api/v1/checklist-templates', ['name' => 'New', 'items' => [['label' => 'A']]])
        ->assertForbidden();

    $this->actingAs($this->user)->getJson('/api/v1/checklist-templates')->assertOk();

    Gate::define('manage-checklist-templates', fn () => true);

    $this->actingAs($this->user)
        ->postJson('/api/v1/checklist-templates', ['name' => 'New', 'items' => [['label' => 'A']]])
        ->assertCreated();
});

test('checklists require a signed-in user', function () {
    $this->getJson('/api/v1/checklists?subject_type=user&subject_id=1')->assertUnauthorized();
    $this->postJson('/api/v1/checklists')->assertUnauthorized();
});
