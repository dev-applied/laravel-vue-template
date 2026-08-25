<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Checklists\Models\Checklist;
use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Checklists\Support\ChecklistSubjects;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(ChecklistSubjects::class)->register('user', User::class);

    $this->template = ChecklistTemplate::query()->create(['name' => 'Site visit']);
    $this->template->items()->create(['label' => 'Photograph the meter', 'requires_evidence' => true, 'position' => 0]);
});

test('attaching a file satisfies the evidence requirement', function () {
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);
    $response  = $checklist->responses->first();

    // The file id is not constrained to the Files module's table: Checklists
    // installs without it, and a foreign key to a table that may not exist is a
    // migration that fails on half the projects that want this module.
    $this->actingAs($this->user)
        ->patchJson("/api/v1/checklists/{$checklist->id}/responses/{$response->id}", [
            'status'  => 'pass',
            'file_id' => 4242,
        ])
        ->assertOk();

    expect($checklist->fresh(['responses'])->outstanding())->toBe([])
        ->and($response->fresh()->file_id)->toBe(4242);

    $this->actingAs($this->user)->postJson("/api/v1/checklists/{$checklist->id}/complete")->assertOk();
});

test('a pass with no file still blocks completion', function () {
    $checklist = Checklist::instantiate($this->template->fresh('items'), $this->user);
    $response  = $checklist->responses->first();

    $this->actingAs($this->user)
        ->patchJson("/api/v1/checklists/{$checklist->id}/responses/{$response->id}", ['status' => 'pass'])
        ->assertOk();

    expect($checklist->fresh(['responses'])->outstanding())->toHaveCount(1);
});
