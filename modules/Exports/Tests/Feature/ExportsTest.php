<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Exports\Jobs\GenerateExport;
use Modules\Exports\Models\Export;
use Modules\Exports\Support\ExportRegistry;

beforeEach(function () {
    $this->user = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    // Register a source against User — the only model the kernel contract
    // guarantees a module can rely on.
    app(ExportRegistry::class)->register(
        key: 'users',
        label: 'Users',
        columns: ['id' => 'ID', 'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email'],
        query: fn (array $filters) => User::query()
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('first_name', 'like', "%{$s}%"))
            ->orderBy('id'),
    );
});

test('the sources endpoint lists what the project registered', function () {
    // Asserted by CONTENT, not by position. This used to pin `sources.0`, which
    // only held while the test's own source was the only one in the registry —
    // the template then registered an Items source from AppServiceProvider and
    // this went red without anything about exports changing. Every real project
    // registers its own, so position was never a property worth asserting.
    $sources = collect(
        $this->actingAs($this->user)
            ->getJson('/api/v1/exports/sources')
            ->assertOk()
            ->json('sources')
    );

    $users = $sources->firstWhere('key', 'users');

    expect($users)->not->toBeNull('the registered source is missing from the listing')
        ->and($users['label'])->toBe('Users');
});

test('the sources route is not shadowed by the export wildcard', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/exports/sources')
        ->assertOk()
        ->assertJsonStructure(['sources']);
});

test('starting an export queues a job and returns a pending row', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson('/api/v1/exports', ['source' => 'users'])
        ->assertCreated()
        ->assertJsonPath('export.status', Export::STATUS_PENDING)
        ->assertJsonPath('export.source', 'users');

    Queue::assertPushed(GenerateExport::class);
});

test('an unregistered source is rejected by validation', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/exports', ['source' => 'secrets'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source']);
});

test('the job writes a csv containing the header row and every record', function () {
    Storage::fake(config('filesystems.default'));
    User::factory()->count(2)->create();

    $export = Export::factory()->create(['user_id' => $this->user->id, 'source' => 'users']);
    (new GenerateExport($export))->handle(app(ExportRegistry::class));

    $export->refresh();

    expect($export->status)->toBe(Export::STATUS_COMPLETED)
        ->and($export->row_count)->toBe(3);

    $contents = Storage::disk($export->disk)->get($export->path);

    expect($contents)->toContain('ID,"First name","Last name",Email')
        ->and($contents)->toContain('Ada');
});

test('filters captured at request time narrow the exported rows', function () {
    Storage::fake(config('filesystems.default'));
    User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

    $export = Export::factory()->create([
        'user_id' => $this->user->id,
        'source'  => 'users',
        'filters' => ['search' => 'Grace'],
    ]);
    (new GenerateExport($export))->handle(app(ExportRegistry::class));

    expect($export->fresh()->row_count)->toBe(1);
    expect(Storage::disk($export->fresh()->disk)->get($export->fresh()->path))
        ->toContain('Grace')
        ->not->toContain('Ada');
});

test('a failing source marks the export failed with the reason', function () {
    app(ExportRegistry::class)->register(
        key: 'broken',
        label: 'Broken',
        columns: ['id' => 'ID'],
        query: fn (array $f) => throw new RuntimeException('source exploded'),
    );

    $export = Export::factory()->create(['user_id' => $this->user->id, 'source' => 'broken']);

    expect(fn () => (new GenerateExport($export))->handle(app(ExportRegistry::class)))
        ->toThrow(RuntimeException::class);

    $export->refresh();

    // The row records a reason, but not the driver's reason. An arbitrary
    // Throwable's message is whatever the driver said — a table name, a
    // connection string, a path on the server — and this field is read by
    // whoever started the export.
    expect($export->status)->toBe(Export::STATUS_FAILED)
        ->and($export->error)->not->toContain('source exploded')
        ->and($export->error)->toContain('Reference:');
});

test('an exception the application threw on purpose is shown as written', function () {
    // The other half: a message the app wrote FOR a person has to survive, or
    // the redaction turns every actionable failure into "something went wrong".
    app(ExportRegistry::class)->register(
        key: 'refusing',
        label: 'Refusing',
        columns: ['id' => 'ID'],
        query: fn (array $f) => throw new App\Exceptions\AppException('That date range is too large to export.', 422),
    );

    $export = Export::factory()->create(['user_id' => $this->user->id, 'source' => 'refusing']);

    expect(fn () => (new GenerateExport($export))->handle(app(ExportRegistry::class)))
        ->toThrow(App\Exceptions\AppException::class);

    expect($export->refresh()->error)->toBe('That date range is too large to export.');
});

test('an export that is not finished cannot be downloaded', function () {
    $export = Export::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->get("/api/v1/exports/{$export->id}/download")
        ->assertStatus(409);
});

test('a completed export downloads its file', function () {
    Storage::fake(config('filesystems.default'));
    $export = Export::factory()->completed()->create(['user_id' => $this->user->id]);
    Storage::disk($export->disk)->put($export->path, "ID,Name\n1,Ada\n");

    $this->actingAs($this->user)
        ->get("/api/v1/exports/{$export->id}/download")
        ->assertOk();
});

test('a user cannot see, download or delete another users export', function () {
    Storage::fake(config('filesystems.default'));
    $foreign = Export::factory()->completed()->create();
    Storage::disk($foreign->disk)->put($foreign->path, 'secret');

    $this->actingAs($this->user)->getJson("/api/v1/exports/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->user)->get("/api/v1/exports/{$foreign->id}/download")->assertNotFound();
    $this->actingAs($this->user)->deleteJson("/api/v1/exports/{$foreign->id}")->assertNotFound();

    expect(Export::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

test('the listing only returns my exports', function () {
    Export::factory()->count(2)->create(['user_id' => $this->user->id]);
    Export::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->getJson('/api/v1/exports')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('deleting an export removes its generated file', function () {
    Storage::fake(config('filesystems.default'));
    $export = Export::factory()->completed()->create(['user_id' => $this->user->id]);
    Storage::disk($export->disk)->put($export->path, 'data');

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/exports/{$export->id}")
        ->assertNoContent();

    Storage::disk($export->disk)->assertMissing($export->path);
});

test('export routes require authentication', function () {
    $this->getJson('/api/v1/exports')->assertUnauthorized();
    $this->postJson('/api/v1/exports', ['source' => 'users'])->assertUnauthorized();
});

test('a storage failure degrades to "not downloadable" instead of failing the request', function () {
    // ExportResource calls isDownloadable() PER ROW, so a listing makes one
    // remote HEAD per record and any of them can throw. Seen for real, driving
    // the page: S3 credentials permitting PutObject but not the HEAD that
    // exists() needs — the export completed, the object was written, and then
    // the listing answered with a raw Flysystem line straight to the user:
    //
    //   Unable to check existence for: exports/1-items-....csv
    //
    // The job's own failures are redacted through safeReason(). This path,
    // outside any try, had nothing.
    $export = Export::factory()->create([
        'user_id' => $this->user->id,
        'status'  => Export::STATUS_COMPLETED,
        'path'    => 'exports/1-items.csv',
        'disk'    => 'broken-disk',
    ]);

    Storage::shouldReceive('disk')
        ->with('broken-disk')
        ->andThrow(new RuntimeException('Unable to check existence for: exports/1-items.csv'));

    expect($export->isDownloadable())->toBeFalse();
});
