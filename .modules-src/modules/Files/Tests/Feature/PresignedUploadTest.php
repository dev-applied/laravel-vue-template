<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Files\Http\Requests\GeneratePresignedUrlRequest;
use Modules\Files\Models\File;
use Modules\Files\Support\FileScanner;

// Only present when the module is installed with storage=s3-presigned; the
// `local` choice drops this file along with the controller and its routes.

beforeEach(function () {
    $this->user = User::factory()->create();
});

// Files are created owned by $this->user: `process` now authorizes through
// modules/Files/Support/FileAccess, and a factory row built outside a request
// records no uploader, so it is denied to everyone — correctly.

test('presigned generation is rejected on a disk without temporary urls', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $this->actingAs($this->user)
        ->postJson('/api/v1/files/generate-presigned-url', [
            'file_size' => 1024,
            'file_name' => 'photo.jpg',
            'file_type' => 'image/jpeg',
        ])
        ->assertStatus(400);
});

test('presigned generation validates its input', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/files/generate-presigned-url', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file_name', 'file_type', 'file_size']);
});

// ── Size, before any bytes move ──────────────────────────────────────────────
// A presigned PUT carried no size constraint at all: the signed URL let the
// browser write an object of any size, the 20MB per-file limit lived only on
// the `local` path's StoreFileRequest, and the storage quota was consulted in
// process() — after the object had landed in the bucket and been paid for.

test('an oversized upload is refused before anything is signed', function () {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $this->actingAs($this->user)
        ->postJson('/api/v1/files/generate-presigned-url', [
            'file_name' => 'huge.zip',
            'file_type' => 'application/zip',
            'file_size' => 20480 * 1024 + 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file_size']);

    // No row reserved: the refusal happens before the File is created, so a
    // rejected upload leaves nothing behind to clean up.
    expect(File::query()->count())->toBe(0);
});

test('the size cap matches the direct upload path', function () {
    // Both storage variants must refuse the same file. StoreFileRequest uses
    // `max:20480` (kilobytes), so the byte cap here is the same number.
    $rules = (new GeneratePresignedUrlRequest)->rules();

    expect($rules['file_size'])->toContain('max:'.(20480 * 1024));
});

test('an over-quota upload is refused before a row is reserved', function () {
    config()->set('files.quota_mb', 1);

    // 900 KB already stored, so a 200 KB upload crosses a 1 MB limit.
    File::factory()->create(['created_by_id' => $this->user->id, 'size' => 900]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/files/generate-presigned-url', [
            'file_name' => 'photo.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 200 * 1000,
        ])
        ->assertStatus(413);

    // Still just the seeded row — nothing was reserved for the refused upload.
    expect(File::query()->count())->toBe(1);
});

test('an upload inside the quota is not refused by the quota', function () {
    // The inverse. A quota that refuses everything would pass the test above
    // while breaking every upload, so this pins that it only refuses the ones
    // that cross the line. It gets past the quota and stops at the disk check,
    // which is a 400 and not a 422 — the local fake has no S3 client.
    config()->set('files.quota_mb', 1);
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    File::factory()->create(['created_by_id' => $this->user->id, 'size' => 100]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/files/generate-presigned-url', [
            'file_name' => 'photo.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 200 * 1000,
        ])
        ->assertStatus(400);
});

test('processing an object that has not landed yet is a conflict', function () {
    Storage::fake(config('filesystems.default'));
    $file = File::factory()->unprocessed()->create(['created_by_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson("/api/v1/files/process/{$file->id}")
        ->assertStatus(409);

    expect($file->fresh()->processed)->toBeFalse();
});

test('processing a landed object records its size and marks it processed', function () {
    Storage::fake(config('filesystems.default'));
    $file = File::factory()->unprocessed()->create(['created_by_id' => $this->user->id, 'type' => 'application/pdf']);
    Storage::disk($file->disk)->put($file->responsive_paths['original'], str_repeat('x', 4000));

    $this->actingAs($this->user)
        ->putJson("/api/v1/files/process/{$file->id}")
        ->assertOk()
        ->assertJsonPath('file.processed', true);

    expect($file->fresh()->processed)->toBeTrue()
        ->and($file->fresh()->size)->toBe(4);
});

test('presigned routes require authentication', function () {
    $this->postJson('/api/v1/files/generate-presigned-url', [])->assertUnauthorized();
});

test('a stranger cannot trigger processing on somebody else\'s file', function () {
    // `process` was a bare route-model bind like the read routes were. Lower
    // stakes than reading the bytes, but it is real image processing on demand
    // against any file id in the system.
    $stranger = User::factory()->create();
    $file     = File::factory()->create(['created_by_id' => $this->user->id]);

    $this->actingAs($stranger)
        ->putJson("/api/v1/files/process/{$file->id}")
        ->assertNotFound();
});

test('the caller cannot choose where in the bucket their signed url writes', function () {
    // `path` became the S3 KEY PREFIX of a presigned PUT, so whatever the
    // caller sent decided where their signed URL could write: another feature's
    // prefix, another tenant's, or anywhere `..` took them — an S3 key is a
    // literal string, so nothing resolves the traversal away. Nothing in the
    // module's own frontend has ever sent this field.
    $this->actingAs($this->user)
        ->postJson('/api/v1/files/generate-presigned-url', [
            'file_size' => 1024,
            'file_name' => 'invoice.pdf',
            'file_type' => 'application/pdf',
            'path'      => '../../backups',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('path');

    $this->actingAs($this->user)
        ->postJson('/api/v1/files/generate-presigned-url', [
            'file_size' => 1024,
            'file_name' => 'invoice.pdf',
            'file_type' => 'application/pdf',
            'path'      => 'someone-elses-prefix',
        ])
        ->assertStatus(422);

    expect(File::count())->toBe(0);
});

test('a project can widen the allow-list without touching the module', function () {
    // The default is one root. Projects that genuinely separate uploads publish
    // config/files.php rather than editing a vendored module.
    //
    // Asserted against the rules rather than over HTTP: past validation the
    // request goes on to talk to S3, so a round trip would be testing the
    // credentials in the test environment instead of this contract.
    $rules = (new GeneratePresignedUrlRequest)->rules();
    $input = ['file_name' => 'invoice.pdf', 'file_type' => 'application/pdf', 'path' => 'documents'];

    expect(Validator::make($input, $rules)->errors()->has('path'))->toBeTrue();

    config()->set('files.upload_prefixes', ['uploads', 'documents']);

    $rules = (new GeneratePresignedUrlRequest)->rules();

    expect(Validator::make($input, $rules)->errors()->has('path'))->toBeFalse();
});

test('a refused object is deleted from the bucket, not just rejected', function () {
    // The presigned design cannot refuse before the write: the browser PUT
    // straight to the bucket, so the object exists before the app has seen a
    // byte. Refusing here therefore has to CLEAN UP, and a rejection that left
    // the object behind would be the worst of both — refused to the user,
    // still costing money and still sitting in the bucket.
    Storage::fake(config('filesystems.default'));

    app()->bind(FileScanner::class, fn () => new class implements FileScanner
    {
        public function refuse(UploadedFile $file): ?string
        {
            return 'That file was refused.';
        }
    });

    $file = File::factory()->unprocessed()->create(['created_by_id' => $this->user->id]);
    $path = $file->responsive_paths['original'] ?? $file->path;
    Storage::disk($file->disk)->put($path, 'pretend bytes');

    $this->actingAs($this->user)
        ->putJson("/api/v1/files/process/{$file->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'That file was refused.');

    expect(File::find($file->id))->toBeNull()
        ->and(Storage::disk($file->disk)->exists($path))->toBeFalse();
});

test('both storage paths refuse an over-quota upload with the same status', function () {
    // The same condition answered 413 on the direct path (FileController) and
    // 422 here, so a client could not handle "over quota" in one place. The
    // codes carry different meanings and the module has to pick one per reason:
    // the quota rejects the SIZE (413), the scanner rejects the CONTENT (422).
    config()->set('files.quota_mb', 1);
    File::factory()->create(['created_by_id' => $this->user->id, 'size' => 900]);

    $presigned = $this->actingAs($this->user)
        ->postJson('/api/v1/files/generate-presigned-url', [
            'file_name' => 'photo.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 200 * 1000,
        ]);

    $direct = $this->actingAs($this->user)
        ->postJson('/api/v1/files', [
            'file' => UploadedFile::fake()->create('photo.jpg', 200),
        ]);

    expect($presigned->status())->toBe(413)
        ->and($direct->status())->toBe($presigned->status());
});
