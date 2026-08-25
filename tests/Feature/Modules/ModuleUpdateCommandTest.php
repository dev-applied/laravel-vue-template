<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * The three-way merge, exercised against a real git repo rather than a mock.
 *
 * `git merge-file` is doing the work, so the value here is in the wiring around
 * it: which of base/head/local a path exists in, whether the option replay keeps
 * a pruned variant pruned, and whether the manifest is re-stamped.
 */
function upd_git(string $dir, string $cmd): void
{
    Process::path($dir)->run($cmd)->throw();
}

function upd_module(string $repo, string $name, array $files, array $manifest = []): void
{
    File::ensureDirectoryExists("{$repo}/modules/{$name}");

    File::put("{$repo}/modules/{$name}/module.json", json_encode(array_merge([
        'name'        => $name,
        'version'     => '0.1.0',
        'description' => 'fixture',
    ], $manifest), JSON_PRETTY_PRINT));

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$repo}/modules/{$name}/{$path}"));
        File::put("{$repo}/modules/{$name}/{$path}", $contents);
    }
}

beforeEach(function () {
    $this->repo = sys_get_temp_dir().'/mod-upd-repo-'.uniqid();
    File::makeDirectory($this->repo, recursive: true);

    upd_git($this->repo, 'git init -q');
    upd_git($this->repo, 'git config user.email t@t.t');
    upd_git($this->repo, 'git config user.name Test');

    $this->local = base_path('modules/UpdFixture');
});

afterEach(function () {
    File::deleteDirectory($this->repo);
    File::deleteDirectory($this->local);
});

/** Commit the current repo state and return its SHA. */
function upd_commit(string $repo, string $message): string
{
    upd_git($repo, 'git add -A');
    upd_git($repo, 'git commit -qm '.escapeshellarg($message));

    return mb_trim(Process::path($repo)->run('git rev-parse HEAD')->output());
}

/** Install the fixture into the project at a given base commit. */
function upd_install(string $repo, string $local, string $base, array $files, array $extraManifest = []): void
{
    File::deleteDirectory($local);
    File::copyDirectory("{$repo}/modules/UpdFixture", $local);

    $manifest                          = json_decode(File::get("{$local}/module.json"), true);
    $manifest['installed_from_commit'] = $base;
    File::put("{$local}/module.json", json_encode(array_merge($manifest, $extraManifest), JSON_PRETTY_PRINT));

    foreach ($files as $path => $contents) {
        File::put("{$local}/{$path}", $contents);
    }
}

test('an upstream change lands on a file the project never touched', function () {
    upd_module($this->repo, 'UpdFixture', ['A.php' => "one\n"]);
    $base = upd_commit($this->repo, 'v1');

    upd_install($this->repo, $this->local, $base, []);

    File::put("{$this->repo}/modules/UpdFixture/A.php", "one\ntwo\n");
    upd_commit($this->repo, 'v2');

    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo])
        ->assertSuccessful();

    expect(File::get("{$this->local}/A.php"))->toBe("one\ntwo\n");
});

test('a local customization survives an unrelated upstream change', function () {
    upd_module($this->repo, 'UpdFixture', ['A.php' => "one\n", 'B.php' => "keep\n"]);
    $base = upd_commit($this->repo, 'v1');

    upd_install($this->repo, $this->local, $base, ['B.php' => "customized\n"]);

    File::put("{$this->repo}/modules/UpdFixture/A.php", "one\ntwo\n");
    upd_commit($this->repo, 'v2');

    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo])
        ->assertSuccessful();

    expect(File::get("{$this->local}/B.php"))->toBe("customized\n");
});

test('overlapping edits conflict rather than silently picking a side', function () {
    upd_module($this->repo, 'UpdFixture', ['A.php' => "shared\n"]);
    $base = upd_commit($this->repo, 'v1');

    upd_install($this->repo, $this->local, $base, ['A.php' => "ours\n"]);

    File::put("{$this->repo}/modules/UpdFixture/A.php", "theirs\n");
    upd_commit($this->repo, 'v2');

    // FAILURE, deliberately: a script must not read "merged with conflicts" as
    // a clean merge.
    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo])
        ->assertFailed();

    $merged = File::get("{$this->local}/A.php");

    expect($merged)->toContain('<<<<<<< yours (this project)')
        ->and($merged)->toContain('ours')
        ->and($merged)->toContain('theirs')
        ->and($merged)->toContain('>>>>>>> upstream');
});

test('a new upstream file is added', function () {
    upd_module($this->repo, 'UpdFixture', ['A.php' => "one\n"]);
    $base = upd_commit($this->repo, 'v1');

    upd_install($this->repo, $this->local, $base, []);

    File::put("{$this->repo}/modules/UpdFixture/New.php", "new\n");
    upd_commit($this->repo, 'v2');

    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo])
        ->assertSuccessful();

    expect(File::exists("{$this->local}/New.php"))->toBeTrue();
});

test('a file deleted upstream is kept locally, not removed', function () {
    // An update should never silently take code away — the project may depend
    // on it. Reported, not deleted.
    upd_module($this->repo, 'UpdFixture', ['A.php' => "one\n", 'Doomed.php' => "bye\n"]);
    $base = upd_commit($this->repo, 'v1');

    upd_install($this->repo, $this->local, $base, []);

    File::delete("{$this->repo}/modules/UpdFixture/Doomed.php");
    upd_commit($this->repo, 'v2');

    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo])
        ->assertSuccessful();

    expect(File::exists("{$this->local}/Doomed.php"))->toBeTrue();
});

test('installed_options are replayed so a pruned variant stays pruned', function () {
    // Without the replay the dropped file reads as a local deletion of a file
    // upstream still has, and the update adds it straight back — re-installing
    // the variant the project explicitly did not choose.
    upd_module($this->repo, 'UpdFixture', ['A.php' => "one\n", 'Extra/Thing.php' => "extra\n"], [
        'options' => [
            'tier' => [
                'type'    => 'select',
                'default' => 'lite',
                'choices' => [
                    'lite' => ['drop' => ['Extra/**']],
                    'full' => [],
                ],
            ],
        ],
    ]);
    $base = upd_commit($this->repo, 'v1');

    upd_install($this->repo, $this->local, $base, [], ['installed_options' => ['tier' => 'lite']]);
    File::deleteDirectory("{$this->local}/Extra");

    File::put("{$this->repo}/modules/UpdFixture/A.php", "one\ntwo\n");
    upd_commit($this->repo, 'v2');

    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo])
        ->assertSuccessful();

    expect(File::exists("{$this->local}/Extra/Thing.php"))->toBeFalse()
        ->and(File::get("{$this->local}/A.php"))->toBe("one\ntwo\n");
});

test('the manifest is re-stamped so the next update has the right base', function () {
    upd_module($this->repo, 'UpdFixture', ['A.php' => "one\n"]);
    $base = upd_commit($this->repo, 'v1');

    upd_install($this->repo, $this->local, $base, []);

    File::put("{$this->repo}/modules/UpdFixture/A.php", "one\ntwo\n");
    $head = upd_commit($this->repo, 'v2');

    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo])
        ->assertSuccessful();

    expect(json_decode(File::get("{$this->local}/module.json"), true)['installed_from_commit'])->toBe($head);
});

test('--dry-run changes nothing on disk', function () {
    upd_module($this->repo, 'UpdFixture', ['A.php' => "one\n"]);
    $base = upd_commit($this->repo, 'v1');

    upd_install($this->repo, $this->local, $base, []);

    File::put("{$this->repo}/modules/UpdFixture/A.php", "one\ntwo\n");
    upd_commit($this->repo, 'v2');

    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo, '--dry-run' => true])
        ->assertSuccessful();

    expect(File::get("{$this->local}/A.php"))->toBe("one\n")
        ->and(json_decode(File::get("{$this->local}/module.json"), true)['installed_from_commit'])->toBe($base);
});

test('a module with no merge base is refused rather than guessed at', function () {
    upd_module($this->repo, 'UpdFixture', ['A.php' => "one\n"]);
    upd_commit($this->repo, 'v1');

    File::deleteDirectory($this->local);
    File::copyDirectory("{$this->repo}/modules/UpdFixture", $this->local);

    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo])
        ->assertFailed();
});

test('an unchanged upstream is a no-op', function () {
    upd_module($this->repo, 'UpdFixture', ['A.php' => "one\n"]);
    $base = upd_commit($this->repo, 'v1');

    upd_install($this->repo, $this->local, $base, ['A.php' => "customized\n"]);

    $this->artisan('module:update', ['names' => ['UpdFixture'], '--from' => $this->repo])
        ->assertSuccessful();

    expect(File::get("{$this->local}/A.php"))->toBe("customized\n");
});
