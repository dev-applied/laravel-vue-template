<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * `module:add` installs composer dependencies for you, which is what makes this
 * gap easy to miss: the packages land in the working copy and everything passes
 * locally. If composer.json is then not COMMITTED, the module ships without the
 * thing it needs and a fresh clone fatals on the first line that touches it.
 *
 * Three CI legs went red exactly that way on 2026-08-25 — `modules/Files` was
 * committed while `imagine/imagine` was not, producing
 * `Class "Imagine\Imagick\Imagine" not found` in legs installing other modules
 * entirely, because the template bundles every module and every leg runs every
 * bundled module's tests.
 */
beforeEach(function () {
    // Per-process name. The suite runs `pest --parallel`, the fixture lives in
    // the REAL modules/ directory (that is the whole point — module:check globs
    // it), and a fixed name meant one process's afterEach deleted the directory
    // while another was between glob() and get(). It passed run alone and failed
    // in the gate.
    $this->sandbox = base_path('modules/__CheckFixture'.getmypid());
    File::ensureDirectoryExists($this->sandbox);
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

function writeManifest(string $dir, array $manifest): void
{
    File::put($dir.'/module.json', json_encode($manifest, JSON_PRETTY_PRINT));
}

test('it passes when every declared dependency is present', function () {
    writeManifest($this->sandbox, [
        'name'              => basename($this->sandbox),
        'composer_requires' => ['laravel/framework'],
    ]);

    $this->artisan('module:check')->assertSuccessful();
});

test('it fails and names the module when a composer dependency was never committed', function () {
    writeManifest($this->sandbox, [
        'name'              => basename($this->sandbox),
        'composer_requires' => ['acme/definitely-not-installed:^1.0'],
    ]);

    $this->artisan('module:check')
        ->assertFailed()
        ->expectsOutputToContain('acme/definitely-not-installed');
});

test('it only demands the dependencies of options this install actually chose', function () {
    // A module offering three storage backends must not require all three. The
    // manifest records `installed_options`, so only the selected choice counts.
    writeManifest($this->sandbox, [
        'name'              => basename($this->sandbox),
        'composer_requires' => [],
        'options'           => [
            'storage' => [
                'choices' => [
                    'local' => ['composer_requires' => []],
                    'cloud' => ['composer_requires' => ['acme/cloud-sdk:^2.0']],
                ],
            ],
        ],
        'installed_options' => ['storage' => 'local'],
    ]);

    $this->artisan('module:check')->assertSuccessful();

    // Switch the install to the option that needs the package, and it is now
    // legitimately missing.
    writeManifest($this->sandbox, [
        'name'              => basename($this->sandbox),
        'composer_requires' => [],
        'options'           => [
            'storage' => [
                'choices' => [
                    'local' => ['composer_requires' => []],
                    'cloud' => ['composer_requires' => ['acme/cloud-sdk:^2.0']],
                ],
            ],
        ],
        'installed_options' => ['storage' => 'cloud'],
    ]);

    $this->artisan('module:check')->assertFailed();
});

test('npm dependencies are checked the same way, scoped names included', function () {
    // A scoped package starts with @, so the version split must skip the first
    // character or `@vueuse/core@^10` reduces to an empty string and silently
    // matches nothing.
    writeManifest($this->sandbox, [
        'name'         => basename($this->sandbox),
        'npm_requires' => ['@acme/not-installed@^1.0'],
    ]);

    $this->artisan('module:check')
        ->assertFailed()
        ->expectsOutputToContain('@acme/not-installed');
});

// ── --defaults: the template's bundle must be the default variants ──────────
// Billing shipped its QA entitlement-switcher variant once, left behind by a
// verification run. Nothing failed: the switcher route self-gates on config, so
// it was inert, and only a `??` line in a git diff gave it away. These assert on
// the fixture BY NAME rather than on the exit code, so they stay true whatever
// variants the rest of the bundle is on.

test('--defaults flags a module sitting off its own stated default', function () {
    writeManifest($this->sandbox, [
        'name'              => basename($this->sandbox),
        'options'           => ['admin' => ['default' => 'none', 'choices' => ['none' => [], 'switcher' => []]]],
        'installed_options' => ['admin' => 'switcher'],
    ]);

    $this->artisan('module:check', ['--defaults' => true])
        ->expectsOutputToContain(basename($this->sandbox))
        ->assertFailed();
});

test('--allow clears one named drift and leaves the others reported', function () {
    // Two fixtures on purpose. Asserting only that the allowed name is ABSENT
    // passes just as well when the check does not run at all — neutering
    // drift() left the single-fixture version of this test green. The second,
    // un-allowed module is what forces the mechanism to have executed.
    $name  = basename($this->sandbox);
    $other = base_path('modules/__CheckOther'.getmypid());
    File::ensureDirectoryExists($other);

    $drifted = fn (string $n) => [
        'name'              => $n,
        'options'           => ['admin' => ['default' => 'none', 'choices' => ['none' => [], 'switcher' => []]]],
        'installed_options' => ['admin' => 'switcher'],
    ];

    writeManifest($this->sandbox, $drifted($name));
    writeManifest($other, $drifted(basename($other)));

    try {
        $this->artisan('module:check', ['--defaults' => true, '--allow' => ["{$name}:admin=switcher"]])
            ->expectsOutputToContain(basename($other))
            ->doesntExpectOutputToContain($name)
            ->assertFailed();
    } finally {
        File::deleteDirectory($other);
    }
});

test('drift is invisible without the flag, so a real project is not broken by it', function () {
    // A client project picks variants on purpose. module:check runs there too,
    // and must not start failing because someone chose the non-default option.
    //
    // Both halves are asserted together: the first proves the drift is real and
    // detectable, the second proves the flag is what gates reporting it. Only
    // the second half would still pass if the check were removed entirely.
    writeManifest($this->sandbox, [
        'name'              => basename($this->sandbox),
        'options'           => ['admin' => ['default' => 'none', 'choices' => ['none' => [], 'switcher' => []]]],
        'installed_options' => ['admin' => 'switcher'],
    ]);

    $this->artisan('module:check', ['--defaults' => true])->assertFailed();
    $this->artisan('module:check')->doesntExpectOutputToContain('non-default')->assertSuccessful();
});

test('a declared exception is a pin, not merely permission', function () {
    // --allow says "this bundle intentionally ships that variant". Treating it
    // as permission only means reinstalling the module at its DEFAULT passes
    // silently — which is what happened to Announcements: a plain
    // `module:add Announcements` dropped the job, the mailable, the migration,
    // the blade view and the test, all tracked files, and the check said fine.
    $name = basename($this->sandbox);

    writeManifest($this->sandbox, [
        'name'              => $name,
        'options'           => ['delivery' => ['default' => 'in-app', 'choices' => ['in-app' => [], 'in-app+email' => []]]],
        'installed_options' => ['delivery' => 'in-app'],
    ]);

    // Pinned to the non-default: sitting on the default is now the failure.
    $this->artisan('module:check', ['--defaults' => true, '--allow' => ["{$name}:delivery=in-app+email"]])
        ->expectsOutputToContain($name)
        ->assertFailed();
});

test('a module on its pinned variant passes', function () {
    $name = basename($this->sandbox);

    writeManifest($this->sandbox, [
        'name'              => $name,
        'options'           => ['delivery' => ['default' => 'in-app', 'choices' => ['in-app' => [], 'in-app+email' => []]]],
        'installed_options' => ['delivery' => 'in-app+email'],
    ]);

    $this->artisan('module:check', ['--defaults' => true, '--allow' => ["{$name}:delivery=in-app+email"]])
        ->doesntExpectOutputToContain($name);
});

test('a file the installed variant drops is reported', function () {
    // The accident this exists for: something copies the module SOURCE over the
    // installed copy — an rsync back from the modules repo, a `git checkout` of
    // the whole directory, an editor sync — and the files the variant dropped
    // come back. Nothing else notices, because every other check reads the
    // manifest and the manifest still names the chosen variant.
    //
    // It bit three times in one night. Reinstating Otp's StepUpTest.php into a
    // `purpose=login` install produced seven failures reporting 405 Method Not
    // Allowed, which reads as a broken route rather than a file that should not
    // be on disk.
    $name = basename($this->sandbox);

    writeManifest($this->sandbox, [
        'name'    => $name,
        'options' => ['purpose' => ['default' => 'login', 'choices' => [
            'login'         => ['drop' => ['Tests/Feature/StepUpTest.php']],
            'login+step-up' => [],
        ]]],
        'installed_options' => ['purpose' => 'login'],
    ]);

    File::ensureDirectoryExists($this->sandbox.'/Tests/Feature');
    file_put_contents($this->sandbox.'/Tests/Feature/StepUpTest.php', "<?php\n");

    $this->artisan('module:check')
        ->expectsOutputToContain('StepUpTest.php')
        ->assertFailed();
});

test('the same module passes once the stray is gone', function () {
    $name = basename($this->sandbox);

    writeManifest($this->sandbox, [
        'name'    => $name,
        'options' => ['purpose' => ['default' => 'login', 'choices' => [
            'login'         => ['drop' => ['Tests/Feature/StepUpTest.php']],
            'login+step-up' => [],
        ]]],
        'installed_options' => ['purpose' => 'login'],
    ]);

    $this->artisan('module:check')->assertSuccessful();
});

test('the check is not behind --defaults', function () {
    // A real project picks its variants on purpose, so option DRIFT there is
    // normal and only the template gates on it. A stray file is not drift — it
    // is a file that should not exist, and it is wrong in a project for exactly
    // the same reason it is wrong here.
    $name = basename($this->sandbox);

    writeManifest($this->sandbox, [
        'name'    => $name,
        'options' => ['purpose' => ['default' => 'login', 'choices' => [
            'login'         => ['drop' => ['Support/StepUpStore.php']],
            'login+step-up' => [],
        ]]],
        // A non-default variant, so --defaults would have complained anyway.
        // Without the flag, only the stray should be reported.
        'installed_options' => ['purpose' => 'login'],
    ]);

    File::ensureDirectoryExists($this->sandbox.'/Support');
    file_put_contents($this->sandbox.'/Support/StepUpStore.php', "<?php\n");

    $this->artisan('module:check')->assertFailed();
});
