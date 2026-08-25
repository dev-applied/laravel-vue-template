<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\DataImport\Jobs\ProcessImport;
use Modules\DataImport\Models\DataImport;
use Modules\DataImport\Support\ImportRegistry;

/** Rows the registered handler wrote, so tests can assert side effects. */
function importedRows(): array
{
    return app('import.spy');
}

beforeEach(function () {
    Storage::fake(config('filesystems.default'));
    $this->user = User::factory()->create();

    app()->instance('import.spy', []);

    app(ImportRegistry::class)->register(
        key: 'people',
        label: 'People',
        fields: ['first_name' => 'First name', 'email' => 'Email'],
        rules: ['first_name' => 'required|string|max:50', 'email' => 'required|email'],
        required: ['first_name', 'email'],
        handler: function (array $row): void {
            $spy   = app('import.spy');
            $spy[] = $row;
            app()->instance('import.spy', $spy);
        },
    );
});

function uploadCsv(string $contents, string $name = 'people.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

test('the targets endpoint describes what a project registered', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/imports/targets')
        ->assertOk()
        // Found by key, not by position. A real project registers several
        // targets, and the app this module is installed into may well register
        // one of its own in AppServiceProvider — asserting `targets.0` made
        // this test fail for a reason that has nothing to do with the endpoint.
        ->assertJsonFragment(['key' => 'people', 'required' => ['first_name', 'email']]);
});

test('the targets route is not shadowed by the import wildcard', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/imports/targets')
        ->assertOk()
        ->assertJsonStructure(['targets']);
});

test('uploading returns the headers and a sample of the file', function () {
    $csv = "First name,Email\nAda,ada@example.com\nGrace,grace@example.com\n";

    $this->actingAs($this->user)
        ->postJson('/api/v1/imports', ['target' => 'people', 'file' => uploadCsv($csv)])
        ->assertCreated()
        ->assertJsonPath('headers', ['First name', 'Email'])
        ->assertJsonPath('sample.0', ['Ada', 'ada@example.com']);
});

test('a utf-8 BOM does not corrupt the first header', function () {
    // Excel writes one, and an unstripped BOM makes the first column never map.
    $csv = "\xEF\xBB\xBFFirst name,Email\nAda,ada@example.com\n";

    $this->actingAs($this->user)
        ->postJson('/api/v1/imports', ['target' => 'people', 'file' => uploadCsv($csv)])
        ->assertCreated()
        ->assertJsonPath('headers.0', 'First name');
});

test('the mapping is pre-filled where a header obviously matches a field', function () {
    $csv = "First name,Email\nAda,ada@example.com\n";

    $this->actingAs($this->user)
        ->postJson('/api/v1/imports', ['target' => 'people', 'file' => uploadCsv($csv)])
        ->assertCreated()
        ->assertJsonPath('suggested.first_name', 'First name')
        ->assertJsonPath('suggested.email', 'Email');
});

test('an unregistered target is rejected', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/imports', ['target' => 'secrets', 'file' => uploadCsv("a\n1\n")])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['target']);
});

test('a dry run reports what would happen and writes nothing', function () {
    $csv    = "First name,Email\nAda,ada@example.com\nBad,not-an-email\n";
    $import = uploadAndGet($this, $csv);

    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/dry-run", [
            'mapping' => ['first_name' => 'First name', 'email' => 'Email'],
        ])
        ->assertOk()
        ->assertJsonPath('result.total', 2)
        ->assertJsonPath('result.imported', 1)
        ->assertJsonPath('result.failed', 1);

    expect(importedRows())->toBe([]);
});

test('running imports the valid rows and reports the invalid ones by line', function () {
    $csv    = "First name,Email\nAda,ada@example.com\nBad,not-an-email\nGrace,grace@example.com\n";
    $import = uploadAndGet($this, $csv);
    $import->update(['mapping' => ['first_name' => 'First name', 'email' => 'Email']]);

    (new ProcessImport($import))->handle(app(ImportRegistry::class));

    $import->refresh();

    // One bad row must not throw away the other two.
    expect($import->status)->toBe(DataImport::STATUS_COMPLETED)
        ->and($import->imported_rows)->toBe(2)
        ->and($import->failed_rows)->toBe(1)
        ->and($import->errors[0]['line'])->toBe(3)
        ->and(importedRows())->toHaveCount(2);
});

test('an empty cell becomes null rather than an empty string', function () {
    app(ImportRegistry::class)->register(
        key: 'nullable-people',
        label: 'Nullable people',
        fields: ['first_name' => 'First name', 'email' => 'Email'],
        rules: ['first_name' => 'required|string', 'email' => 'nullable|email'],
        handler: function (array $row): void {
            $spy   = app('import.spy');
            $spy[] = $row;
            app()->instance('import.spy', $spy);
        },
    );

    $csv    = "First name,Email\nAda,\n";
    $import = uploadAndGet($this, $csv, 'nullable-people');
    $import->update(['mapping' => ['first_name' => 'First name', 'email' => 'Email']]);

    (new ProcessImport($import))->handle(app(ImportRegistry::class));

    expect($import->fresh()->imported_rows)->toBe(1)
        ->and(importedRows()[0]['email'])->toBeNull();
});

test('running without a required field mapped is refused', function () {
    $import = uploadAndGet($this, "First name,Email\nAda,ada@example.com\n");

    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/run", ['mapping' => ['first_name' => 'First name']])
        ->assertStatus(422);
});

test('running queues the job', function () {
    Queue::fake();
    $import = uploadAndGet($this, "First name,Email\nAda,ada@example.com\n");

    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/run", [
            'mapping' => ['first_name' => 'First name', 'email' => 'Email'],
        ])
        ->assertStatus(202);

    Queue::assertPushed(ProcessImport::class);
});

test('retained errors are capped but the failed count is not', function () {
    $rows   = collect(range(1, 120))->map(fn ($i) => "Name{$i},not-an-email")->implode("\n");
    $import = uploadAndGet($this, "First name,Email\n{$rows}\n");
    $import->update(['mapping' => ['first_name' => 'First name', 'email' => 'Email']]);

    (new ProcessImport($import))->handle(app(ImportRegistry::class));

    $import->refresh();

    expect($import->failed_rows)->toBe(120)
        ->and($import->errors)->toHaveCount(DataImport::MAX_RETAINED_ERRORS);
});

test('a handler that throws fails only its own row', function () {
    app(ImportRegistry::class)->register(
        key: 'explosive',
        label: 'Explosive',
        fields: ['first_name' => 'First name', 'email' => 'Email'],
        rules: ['first_name' => 'required', 'email' => 'required|email'],
        handler: function (array $row): void {
            if ($row['first_name'] === 'Boom') {
                throw new RuntimeException('handler exploded');
            }
            $spy   = app('import.spy');
            $spy[] = $row;
            app()->instance('import.spy', $spy);
        },
    );

    $csv    = "First name,Email\nAda,ada@example.com\nBoom,boom@example.com\n";
    $import = uploadAndGet($this, $csv, 'explosive');
    $import->update(['mapping' => ['first_name' => 'First name', 'email' => 'Email']]);

    (new ProcessImport($import))->handle(app(ImportRegistry::class));

    expect($import->fresh()->imported_rows)->toBe(1)
        ->and($import->fresh()->failed_rows)->toBe(1)
        // The row number is the useful half and it survives; the driver's
        // text does not. A constraint violation names the table, the index and
        // the offending value back at whoever uploaded the file.
        ->and($import->fresh()->errors[0]['errors'][0])->not->toContain('handler exploded')
        ->and($import->fresh()->errors[0]['errors'][0])->toContain('Reference:')
        ->and($import->fresh()->errors[0]['line'])->toBe(3);
});

test('a handler refusing on purpose says why, in its own words', function () {
    // Redaction must not swallow the messages a project wrote FOR the person
    // uploading the file — those are the ones they can act on.
    app(ImportRegistry::class)->register(
        key: 'picky',
        label: 'Picky',
        fields: ['first_name' => 'First name', 'email' => 'Email'],
        rules: ['first_name' => 'required', 'email' => 'required|email'],
        handler: fn (array $row) => throw new App\Exceptions\AppException('That email is already on another account.', 422),
    );

    $csv    = "First name,Email\nAda,ada@example.com\n";
    $import = uploadAndGet($this, $csv, 'picky');
    $import->update(['mapping' => ['first_name' => 'First name', 'email' => 'Email']]);

    (new ProcessImport($import))->handle(app(ImportRegistry::class));

    expect($import->fresh()->errors[0]['errors'][0])->toBe('That email is already on another account.');
});

test('a user cannot see or run another users import', function () {
    $foreign = DataImport::factory()->create();

    $this->actingAs($this->user)->getJson("/api/v1/imports/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$foreign->id}/run", ['mapping' => ['a' => 'b']])
        ->assertNotFound();
});

test('deleting an import removes its uploaded file', function () {
    $import = uploadAndGet($this, "First name,Email\nAda,ada@example.com\n");

    $this->actingAs($this->user)->deleteJson("/api/v1/imports/{$import->id}")->assertNoContent();

    Storage::disk($import->disk)->assertMissing($import->path);
});

test('import routes require authentication', function () {
    $this->getJson('/api/v1/imports')->assertUnauthorized();
    $this->getJson('/api/v1/imports/targets')->assertUnauthorized();
});

/** Upload a file and return its DataImport row. */
function uploadAndGet($test, string $csv, string $target = 'people'): DataImport
{
    $test->actingAs($test->user)
        ->postJson('/api/v1/imports', ['target' => $target, 'file' => uploadCsv($csv)])
        ->assertCreated();

    return DataImport::query()->latest('id')->first();
}

test('a failed disk write is an error, not an import pointing at nothing', function () {
    // store() returns FALSE when the disk write fails, and false in a string
    // column becomes "0". Unchecked, the upload LOOKED successful: a row was
    // created pointing at a file named "0", headers came back empty so nothing
    // could be mapped, and every later step failed for reasons unrelated to the
    // actual cause. Found by driving the wizard against a disk with no
    // credentials, not by any test.
    // UploadedFile::store() delegates to Storage::disk()->putFileAs(), so that
    // is the one call that has to fail — returning false rather than throwing,
    // which is precisely what made this survivable enough to ship.
    $disk = Mockery::mock(Illuminate\Contracts\Filesystem\Filesystem::class);
    $disk->shouldReceive('putFileAs')->andReturnFalse();
    Storage::shouldReceive('disk')->andReturn($disk);

    $this->actingAs($this->user)
        ->postJson('/api/v1/imports', [
            'target' => 'people',
            'file'   => UploadedFile::fake()->createWithContent('people.csv', "First name,Email\nAda,ada@example.com\n"),
        ])
        ->assertStatus(500);

    // The point of the guard: no row, rather than a row pointing at a file
    // called "0" that makes every later step fail for an unrelated-looking
    // reason.
    expect(DataImport::query()->count())->toBe(0);
});

test('a csv with backslashes keeps them, and headers still parse', function () {
    // fgetcsv's $escape default is deprecated in PHP 8.4 and changes in PHP 9.
    // Passing '' selects RFC 4180, where a backslash is a literal character —
    // which is what a spreadsheet exports. The old default treated it as an
    // escape and silently ate the next character.
    $path = sys_get_temp_dir().'/csvreader-'.uniqid().'.csv';
    file_put_contents($path, "name,path\nWidget,C:\\temp\\file.txt\n");

    $reader = new Modules\DataImport\Support\CsvReader($path);
    $rows   = iterator_to_array($reader->rows(10));

    expect($reader->headers())->toBe(['name', 'path'])
        ->and($rows[1][1])->toBe('C:\\temp\\file.txt');

    @unlink($path);
});

// ── Double dispatch and replay ───────────────────────────────────────────────
// Every row commits in its own transaction, which is what lets a file with one
// bad row import the rest. It is also what makes both of these bugs write real
// duplicate data rather than roll back.

/** Upload a file and return the import, ready to be run. */
function uploadedImport(string $csv = "First name,Email\nAda,ada@example.com\nGrace,grace@example.com\n"): DataImport
{
    test()->actingAs(test()->user)
        ->postJson('/api/v1/imports', ['target' => 'people', 'file' => uploadCsv($csv)])
        ->assertCreated();

    return DataImport::latest('id')->firstOrFail();
}

const PEOPLE_MAPPING = ['mapping' => ['first_name' => 'First name', 'email' => 'Email']];

test('a second start is refused instead of importing the file twice', function () {
    // Two clicks used to dispatch two jobs over the same file. Nothing
    // deduplicated them and nothing reported it — the only symptom was every
    // row appearing twice in the target table.
    Queue::fake();

    $import = uploadedImport();

    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/run", PEOPLE_MAPPING)
        ->assertStatus(202);

    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/run", PEOPLE_MAPPING)
        ->assertStatus(409);

    Queue::assertPushed(ProcessImport::class, 1);
});

test('a completed import cannot be run again', function () {
    Queue::fake();

    $import = uploadedImport();
    $import->update(['status' => DataImport::STATUS_COMPLETED]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/run", PEOPLE_MAPPING)
        ->assertStatus(409);

    Queue::assertNothingPushed();
});

test('a failed import can be started again', function () {
    // The refusal must not become a trap: a genuine failure has to be
    // recoverable, and the checkpoint is what makes retrying it safe.
    Queue::fake();

    $import = uploadedImport();
    $import->update(['status' => DataImport::STATUS_FAILED, 'failure_reason' => 'disk went away']);

    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/run", PEOPLE_MAPPING)
        ->assertStatus(202);

    expect($import->fresh()->failure_reason)->toBeNull();
    Queue::assertPushed(ProcessImport::class, 1);
});

test('a retry resumes where the last attempt died instead of at line 1', function () {
    // The expensive bug. A worker killed at row 18,000 of 20,000 has already
    // committed 18,000 rows; without a checkpoint its retry writes them all a
    // second time and reports a clean success.
    $import = uploadedImport("First name,Email\nAda,ada@example.com\nGrace,grace@example.com\nAlan,alan@example.com\n");

    $import->update([
        'mapping' => PEOPLE_MAPPING['mapping'],
        'status'  => DataImport::STATUS_PROCESSING,
        // Line 1 is the header, so lines 2 and 3 are Ada and Grace: this is
        // the state a worker killed just after Grace would have left behind.
        'processed_rows' => 3,
        'total_rows'     => 2,
        'imported_rows'  => 2,
    ]);

    (new ProcessImport($import))->handle(app(ImportRegistry::class));

    expect(importedRows())->toHaveCount(1)
        ->and(importedRows()[0]['email'])->toBe('alan@example.com');
});

test('the counters stay whole across a resume', function () {
    // Seeding the counters from the previous attempt is not cosmetic: reset
    // them and a resumed import reports "1 row imported" for a 20,000-row
    // file, which reads exactly like a failure that nobody can find.
    $import = uploadedImport("First name,Email\nAda,ada@example.com\nGrace,grace@example.com\nAlan,alan@example.com\n");

    $import->update([
        'mapping'        => PEOPLE_MAPPING['mapping'],
        'status'         => DataImport::STATUS_PROCESSING,
        'processed_rows' => 3,
        'total_rows'     => 2,
        'imported_rows'  => 2,
    ]);

    (new ProcessImport($import))->handle(app(ImportRegistry::class));

    $fresh = $import->fresh();

    expect($fresh->total_rows)->toBe(3)
        ->and($fresh->imported_rows)->toBe(3)
        ->and($fresh->status)->toBe(DataImport::STATUS_COMPLETED);
});

test('a finished import records how far it got', function () {
    $import = uploadedImport();
    $import->update(['mapping' => PEOPLE_MAPPING['mapping'], 'status' => DataImport::STATUS_PROCESSING]);

    (new ProcessImport($import))->handle(app(ImportRegistry::class));

    // Line 3 is the last row of a two-row file with a header.
    expect($import->fresh()->processed_rows)->toBe(3);
});

test('a dry run neither resumes nor records progress', function () {
    // A preview that honoured the checkpoint would show the tail of the file
    // as though it were the whole thing, and one that wrote a checkpoint would
    // make the real import skip rows it never imported.
    $import = uploadedImport("First name,Email\nAda,ada@example.com\nGrace,grace@example.com\nAlan,alan@example.com\n");

    $import->update([
        'mapping'        => PEOPLE_MAPPING['mapping'],
        'processed_rows' => 3,
    ]);

    $result = (new ProcessImport($import, dryRun: true))->run(app(ImportRegistry::class)->get('people'));

    expect($result['total'])->toBe(3)
        ->and($import->fresh()->processed_rows)->toBe(3)
        ->and(importedRows())->toBeEmpty();
});

test('re-mapping an import that has already written rows is refused', function () {
    // The already-written rows are committed and cannot be taken back, so
    // applying a new mapping to the tail would leave one file imported two
    // different ways with nothing saying so.
    Queue::fake();

    $import = uploadedImport();
    $import->update([
        'mapping'        => PEOPLE_MAPPING['mapping'],
        'status'         => DataImport::STATUS_FAILED,
        'processed_rows' => 2,
        'imported_rows'  => 1,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/run", [
            'mapping' => ['first_name' => 'Email', 'email' => 'Email'],
        ])
        ->assertStatus(409);

    Queue::assertNothingPushed();

    // The same mapping is a resume, not a re-map, and is allowed.
    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/run", PEOPLE_MAPPING)
        ->assertStatus(202);
});

test('the same mapping is still the same mapping with its keys in another order', function () {
    // MySQL 8 stores JSON in a normalised binary form that SORTS object keys;
    // MariaDB stores it as text and preserves them. A strict !== on the two
    // arrays therefore fired on every legitimate resume on MySQL — which is
    // what clients deploy — while passing on the MariaDB used for local
    // development. It shipped green and CI caught it on the first run.
    //
    // Written so it fails on EITHER engine: the submitted order is deliberately
    // the reverse of the stored one, so nothing about it depends on which
    // engine normalises what.
    Queue::fake();

    $import = uploadedImport();
    $import->update([
        'mapping'        => ['first_name' => 'First name', 'email' => 'Email'],
        'status'         => DataImport::STATUS_FAILED,
        'processed_rows' => 2,
        'imported_rows'  => 1,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/run", [
            'mapping' => ['email' => 'Email', 'first_name' => 'First name'],
        ])
        ->assertStatus(202);
});

test('the import is marked failed once the attempts are exhausted, not on the first throw', function () {
    // This lived in a catch block, which runs on EVERY attempt: the first
    // transient blip flipped the status to failed while two retries were still
    // queued, so the UI said the import had failed and then rows kept arriving.
    $import = uploadedImport();
    $import->update(['mapping' => PEOPLE_MAPPING['mapping'], 'status' => DataImport::STATUS_PROCESSING]);

    (new ProcessImport($import))->failed(new RuntimeException('worker died'));

    $fresh = $import->fresh();

    expect($fresh->status)->toBe(DataImport::STATUS_FAILED)
        // Redacted: a worker dies on driver exceptions, not on prose.
        ->and($fresh->failure_reason)->not->toContain('worker died')
        ->and($fresh->failure_reason)->toContain('Reference:');
});
