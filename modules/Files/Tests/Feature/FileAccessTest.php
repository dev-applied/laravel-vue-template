<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Files\Models\File;
use Modules\Files\Support\FileAccess;

/**
 * The module shipped with NO authorization: every route was `auth:sanctum` plus
 * a bare route-model bind. Ids are sequential, so any authenticated user could
 * walk /files/download/1..N and read every file in the system, then DELETE any
 * of them — which unlinks the bytes as well as the row.
 */
beforeEach(function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
});

function uploadAs(User $user): File
{
    return test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('secret.pdf', 10)])
        ->assertOk()
        ->json('file.id')
        ? File::query()->latest('id')->firstOrFail()
        : throw new RuntimeException('upload failed');
}

test('a stranger cannot download somebody else\'s file', function () {
    $owner    = User::factory()->create();
    $stranger = User::factory()->create();
    $file     = uploadAs($owner);

    $this->actingAs($stranger, 'sanctum')
        ->get("/api/v1/files/download/{$file->id}")
        ->assertNotFound();
});

test('a refusal is a 404, so ids cannot be enumerated', function () {
    // 403 would answer "this id exists" for every id a caller cares to try,
    // which IS the attack — the ids are sequential.
    $owner    = User::factory()->create();
    $stranger = User::factory()->create();
    $file     = uploadAs($owner);

    $exists  = $this->actingAs($stranger, 'sanctum')->getJson("/api/v1/files/meta/{$file->id}");
    $missing = $this->actingAs($stranger, 'sanctum')->getJson('/api/v1/files/meta/999999');

    expect($exists->status())->toBe($missing->status())
        ->and($exists->status())->toBe(404);
});

test('a stranger cannot delete somebody else\'s file', function () {
    $owner    = User::factory()->create();
    $stranger = User::factory()->create();
    $file     = uploadAs($owner);

    $this->actingAs($stranger, 'sanctum')
        ->deleteJson("/api/v1/files/{$file->id}")
        ->assertNotFound();

    expect(File::query()->whereKey($file->id)->exists())->toBeTrue();
});

test('the owner can still read and delete their own file', function () {
    $owner = User::factory()->create();
    $file  = uploadAs($owner);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/files/meta/{$file->id}")
        ->assertOk();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/files/{$file->id}")
        ->assertNoContent();
});

test('a file with no recorded uploader is denied, not shared', function () {
    // created_by is null for anything written by a job or a seeder. Denying is
    // the direction to be wrong in.
    $user = User::factory()->create();
    $file = uploadAs($user);
    $file->forceFill([$file->getCreatedByColumn() => null])->saveQuietly();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/files/meta/{$file->id}")
        ->assertNotFound();
});

test('a project can bind its own rule', function () {
    // The seam is the point: a file's audience is a property of whatever it is
    // attached to, and only the project knows that.
    app()->instance(FileAccess::class, new class implements FileAccess
    {
        public function canView(File $file, ?Authenticatable $user): bool
        {
            return true;
        }

        public function canDelete(File $file, ?Authenticatable $user): bool
        {
            return false;
        }
    });

    $owner    = User::factory()->create();
    $stranger = User::factory()->create();
    $file     = uploadAs($owner);

    $this->actingAs($stranger, 'sanctum')->getJson("/api/v1/files/meta/{$file->id}")->assertOk();
    $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/files/{$file->id}")->assertNotFound();
});
