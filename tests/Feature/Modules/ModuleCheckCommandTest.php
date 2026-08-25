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
    $this->sandbox = base_path('modules/__CheckFixture');
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
        'name'              => '__CheckFixture',
        'composer_requires' => ['laravel/framework'],
    ]);

    $this->artisan('module:check')->assertSuccessful();
});

test('it fails and names the module when a composer dependency was never committed', function () {
    writeManifest($this->sandbox, [
        'name'              => '__CheckFixture',
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
        'name'              => '__CheckFixture',
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
        'name'              => '__CheckFixture',
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
        'name'         => '__CheckFixture',
        'npm_requires' => ['@acme/not-installed@^1.0'],
    ]);

    $this->artisan('module:check')
        ->assertFailed()
        ->expectsOutputToContain('@acme/not-installed');
});
