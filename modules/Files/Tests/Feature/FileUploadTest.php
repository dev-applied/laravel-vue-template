<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Files\Models\File;
use Modules\Files\Support\FileScanner;

// Files are created owned by $this->user: the module now authorizes on the
// WhoDidIt uploader (modules/Files/Support/OwnerFileAccess), and a factory row
// built outside a request has no Auth::id() to record, so an unowned file is
// denied to everyone — correctly.
beforeEach(function () {
    Storage::fake(config('filesystems.default'));
    $this->user = User::factory()->create();
});

test('a file upload stores the object and returns its record', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/files', [
            'file' => UploadedFile::fake()->create('contract.pdf', 12, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('file.name', 'contract.pdf')
        ->assertJsonPath('file.processed', true);

    $file = File::query()->sole();
    Storage::disk($file->disk)->assertExists($file->path);
});

test('a non-image upload is marked processed without generating variants', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/files', [
            'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])
        ->assertOk();

    $file = File::query()->sole();

    expect($file->processed)->toBeTrue()
        ->and(array_keys($file->responsive_paths))->toBe(['original']);
});

test('an image upload generates every configured size variant', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->image('photo.jpg', 800, 600)])
        ->assertOk();

    $file = File::query()->sole();

    foreach (array_keys(File::SIZES) as $size) {
        expect($file->responsive_paths)->toHaveKey($size);
        Storage::disk($file->disk)->assertExists($file->responsive_paths[$size]);
    }
})->skip(
    // The CLASS, not the extension. Variant generation needs imagine/imagine
    // (a composer package) as well as ext-imagick, and CI installs the
    // extension — so this guard was satisfied while the library was absent,
    // and the test fataled with "Class Imagine\\Imagick\\Imagine not found"
    // instead of skipping. A guard that checks a different thing from what the
    // code needs is worse than no guard: it reports the wrong cause.
    fn () => ! class_exists(Imagine\Imagick\Imagine::class),
    'imagine/imagine and ext-imagick are required to generate variants'
);

test('uploads are rejected over the size limit', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('huge.zip', 30000)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('file routes require authentication', function () {
    $this->postJson('/api/v1/files', [])->assertUnauthorized();
    $this->getJson('/api/v1/files/meta/1')->assertUnauthorized();
});

test('the metadata endpoint returns json rather than a redirect', function () {
    $file = File::factory()->create(['created_by_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/files/meta/{$file->id}")
        ->assertOk()
        ->assertJsonPath('file.id', $file->id)
        ->assertJsonPath('file.processed', true);
});

test('the view route is not shadowed by the greedy size route', function () {
    // Regression: the kernel registered /files/{file}/{size?} BEFORE
    // /files/view/{file}, so `view` resolved as file="view", size="<id>" and
    // 404'd on model binding. Literal-prefixed routes must win.
    $file = File::factory()->create(['created_by_id' => $this->user->id]);
    Storage::disk($file->disk)->put($file->path, 'contents');

    $response = $this->actingAs($this->user)->get("/api/v1/files/view/{$file->id}");

    // Either a stream (200) or a redirect to a temporary URL (302) is correct
    // depending on the disk. Only a 404 would mean the greedy route swallowed
    // it and model binding failed on file="view".
    expect($response->status())->not->toBe(404);
});

test('deleting a file removes it and every variant from storage', function () {
    $file = File::factory()->create(['created_by_id' => $this->user->id,
        'responsive_paths'                           => ['original' => 'uploads/a/a.jpg', 'thumbnail' => 'uploads/a/a_thumbnail.jpg'],
        'path'                                       => 'uploads/a/a.jpg',
    ]);
    $disk = Storage::disk($file->disk);
    $disk->put('uploads/a/a.jpg', 'x');
    $disk->put('uploads/a/a_thumbnail.jpg', 'x');

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/files/{$file->id}")
        ->assertNoContent();

    $disk->assertMissing('uploads/a/a.jpg');
    $disk->assertMissing('uploads/a/a_thumbnail.jpg');
    expect(File::query()->count())->toBe(0);
});

test('two files with the same name in one request do not overwrite each other', function () {
    // The path was `<name>_<unix seconds>.<ext>` and the DIRECTORY was derived
    // from that same string, so same-named uploads inside one second landed on
    // one object: N rows, one file, everyone resolving to the last write — and
    // deleting any one row unlinked the bytes for all of them. A phone posting
    // four photos called image.jpg hits this on the first try.
    collect(range(1, 4))->each(function () {
        $this->actingAs($this->user)
            ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->image('image.jpg')])
            ->assertOk();
    });

    // Read `path` from the DB, not the response: FileResource deliberately does
    // not expose the storage path.
    $paths = File::query()->pluck('path');

    expect($paths)->toHaveCount(4)
        ->and($paths->unique())->toHaveCount(4);
});

test('the destination folder the uploader chose is actually recorded', function () {
    // Both upload paths have always SENT `folder_id` — useFileUpload puts it in
    // the payload and AppFileUploadBtn exposes it as a prop — and the server
    // had no column, no fillable entry and no validation rule, so Laravel
    // discarded it on every upload. A project could set the prop, watch the
    // upload succeed, and find every file in one undifferentiated pile.
    Storage::fake(config('filesystems.default'));

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/files', [
            'file'      => UploadedFile::fake()->image('receipt.jpg'),
            'folder_id' => 42,
        ])
        ->assertOk();

    expect($response->json('file.folder_id'))->toBe(42)
        ->and(File::query()->latest('id')->firstOrFail()->folder_id)->toBe(42);
});

test('an upload with no folder is still fine', function () {
    // Nullable on purpose: most projects have no folder feature at all, and the
    // column must not become a required ceremony for them.
    Storage::fake(config('filesystems.default'));

    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->image('loose.jpg')])
        ->assertOk()
        ->assertJsonPath('file.folder_id', null);
});

// ── Refusing a file ──────────────────────────────────────────────────────────
// The module ships no scanner and no quota, because which antivirus a project
// can run and how much storage a user gets are not a vendored module's call.
// What it ships is the place both decisions live.

test('a bound scanner refuses an upload before anything is written', function () {
    Storage::fake(config('filesystems.default'));

    app()->bind(FileScanner::class, fn () => new class implements FileScanner
    {
        public function refuse(UploadedFile $file): ?string
        {
            return str_contains($file->getClientOriginalName(), 'bad')
                ? 'That file was refused.'
                : null;
        }
    });

    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('bad.exe', 10)])
        ->assertStatus(422)
        ->assertJsonPath('message', 'That file was refused.');

    // Refused BEFORE the write, not cleaned up after it.
    expect(File::count())->toBe(0)
        ->and(Storage::disk(config('filesystems.default'))->allFiles())->toBeEmpty();

    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('fine.pdf', 10)])
        ->assertOk();
});

test('the shipped scanner refuses nothing', function () {
    // Pinned so it cannot drift: a module that rejected uploads on install
    // would look broken, and most projects take files only from people they
    // already trust.
    Storage::fake(config('filesystems.default'));

    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('anything.exe', 10)])
        ->assertOk();
});

test('a storage quota is enforced across a users files, not just per file', function () {
    // There has always been a 20MB per-FILE cap and nothing aggregate, so any
    // signed-in user could fill the disk one 20MB file at a time. On
    // s3-presigned that stops being a disk problem and becomes a bill.
    Storage::fake(config('filesystems.default'));
    config()->set('files.quota_mb', 1);

    // ~600KB lands fine.
    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('first.bin', 600)])
        ->assertOk();

    // A second ~600KB crosses 1MB in aggregate, though neither file is close
    // to the per-file limit.
    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('second.bin', 600)])
        ->assertStatus(413);

    expect(File::count())->toBe(1);
});

test('the quota is per uploader, not global', function () {
    Storage::fake(config('filesystems.default'));
    config()->set('files.quota_mb', 1);

    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('mine.bin', 900)])
        ->assertOk();

    // Somebody else's allowance is untouched by the first user's.
    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('theirs.bin', 900)])
        ->assertOk();
});

test('no quota is configured by default', function () {
    // Off unless asked for: a cap appearing on install would break every
    // project that never wanted one.
    Storage::fake(config('filesystems.default'));

    expect(config('files.quota_mb'))->toBeNull();

    $this->actingAs($this->user)
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('big.bin', 15000)])
        ->assertOk();
});
