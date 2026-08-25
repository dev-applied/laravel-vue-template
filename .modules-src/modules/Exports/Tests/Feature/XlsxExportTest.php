<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Exports\Jobs\GenerateExport;
use Modules\Exports\Models\Export;
use Modules\Exports\Support\ExportRegistry;

// Only present when installed with formats=csv+xlsx; the `csv` choice drops
// this file along with XlsxWriter and the openspout dependency.

test('an xlsx export produces a real xlsx file', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();

    app(ExportRegistry::class)->register(
        key: 'users',
        label: 'Users',
        columns: ['id' => 'ID', 'first_name' => 'First name'],
        query: fn (array $f) => User::query()->orderBy('id'),
    );

    $export = Export::factory()->create([
        'user_id' => $user->id, 'source' => 'users', 'format' => 'xlsx',
    ]);
    (new GenerateExport($export))->handle(app(ExportRegistry::class));

    $export->refresh();

    expect($export->status)->toBe(Export::STATUS_COMPLETED)
        ->and($export->path)->toEndWith('.xlsx');

    // XLSX is a zip archive — check the magic bytes rather than trusting the
    // extension the module itself chose.
    expect(mb_substr(Storage::disk($export->disk)->get($export->path), 0, 2))->toBe('PK');
})->skip(fn () => ! class_exists(OpenSpout\Writer\XLSX\Writer::class), 'openspout not installed');
