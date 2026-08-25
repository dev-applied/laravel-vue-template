<?php

declare(strict_types=1);

use App\Console\Commands\Modules\ModuleAddCommand;

/**
 * The npm-spec guard, exercised through the private writer rather than a full
 * `module:add` run.
 *
 * A whole install touches composer, the autoloader and the filesystem; what is
 * under test is one parse, and reaching it directly keeps the test honest about
 * what it proves. The guard itself lives on the code path every install uses.
 */
function writeNpm(array $specs, string $packageJson): string
{
    $path = sys_get_temp_dir().'/npm-spec-'.uniqid().'.json';
    file_put_contents($path, $packageJson);

    $command = new ModuleAddCommand;
    $method  = new ReflectionMethod($command, 'recordNpmDependencies');
    $method->setAccessible(true);

    try {
        $method->invoke($command, $specs, 'dependencies', $path);
    } finally {
        $contents = (string) file_get_contents($path);
        @unlink($path);
    }

    return $contents;
}

test('a composer-syntax npm requirement is refused rather than written', function () {
    // `laravel-echo:^2.2` used to be written into package.json as the KEY
    // `"laravel-echo:^2.2": "*"` — a name npm will never resolve, sorted quietly
    // into the middle of the dependency list. Nothing downstream noticed:
    // `npm install` succeeds treating it as an absent package, the import fails
    // at build time looking like a missing file, and `module:check` reports the
    // REAL package as missing while the manifest reads correctly.
    expect(fn () => writeNpm(['laravel-echo:^2.2'], '{"dependencies":{}}'))
        ->toThrow(RuntimeException::class, 'not a valid npm spec');
});

test('valid specs land, scoped names and bare names included', function () {
    $result = json_decode(
        writeNpm(['laravel-echo@^2.2', '@vueuse/core@^13.0.0', 'nanoid'], '{"dependencies":{}}'),
        true,
    );

    expect($result['dependencies'])->toBe([
        // Sorted, which is what keeps the file from churning between installs.
        '@vueuse/core' => '^13.0.0',
        'laravel-echo' => '^2.2',
        // No constraint means "whatever npm resolves", not a broken key.
        'nanoid' => '*',
    ]);
});
