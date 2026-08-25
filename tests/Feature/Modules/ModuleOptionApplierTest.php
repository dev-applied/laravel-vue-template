<?php

declare(strict_types=1);

use App\Support\Modules\ModuleOptionApplier;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/mod-applier-'.uniqid();
    File::makeDirectory($this->dir.'/OAuth', recursive: true);
    File::makeDirectory($this->dir.'/resources/views/oauth', recursive: true);
    File::makeDirectory($this->dir.'/Console/Commands', recursive: true);
    File::put($this->dir.'/OAuth/Layer.php', '<?php');
    File::put($this->dir.'/resources/views/oauth/authorize.blade.php', 'x');
    File::put($this->dir.'/Console/Commands/EnableOAuthCommand.php', '<?php');
    File::put($this->dir.'/Keep.php', '<?php');

    $this->env = $this->dir.'/.env';
    File::put($this->env, "APP_NAME=Test\nEXISTING=old\n");

    $this->schema = [
        'auth' => [
            'type'    => 'select',
            'choices' => [
                'sanctum'       => ['drop' => ['OAuth/**', 'resources/views/oauth/**', 'Console/Commands/EnableOAuthCommand.php']],
                'sanctum+oauth' => [
                    'require' => ['laravel/passport:^13.7'],
                    'env'     => ['AUTH_OAUTH_ENABLED' => 'true', 'EXISTING' => 'new'],
                    'run'     => ['auth:enable-oauth --force'],
                ],
            ],
        ],
    ];
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

test('the sanctum choice drops the oauth files and requires nothing', function () {
    $plan = (new ModuleOptionApplier)->apply($this->dir, $this->schema, ['auth' => 'sanctum'], $this->env);

    expect(File::isDirectory($this->dir.'/OAuth'))->toBeFalse();
    expect(File::isDirectory($this->dir.'/resources/views/oauth'))->toBeFalse();
    expect(File::exists($this->dir.'/Console/Commands/EnableOAuthCommand.php'))->toBeFalse();
    expect(File::exists($this->dir.'/Keep.php'))->toBeTrue();
    expect($plan['require'])->toBe([]);
    expect($plan['run'])->toBe([]);
});

test('the oauth choice keeps files, collects deps + run, and writes env', function () {
    $plan = (new ModuleOptionApplier)->apply($this->dir, $this->schema, ['auth' => 'sanctum+oauth'], $this->env);

    expect(File::isDirectory($this->dir.'/OAuth'))->toBeTrue();
    expect($plan['require'])->toBe(['laravel/passport:^13.7']);
    expect($plan['run'])->toBe(['auth:enable-oauth --force']);

    $env = File::get($this->env);
    expect($env)->toContain('AUTH_OAUTH_ENABLED=true');   // appended
    expect($env)->toContain('EXISTING=new');              // replaced in place
    expect($env)->not->toContain('EXISTING=old');
});

test('droppedFiles expands globs + dirs to relative file paths for the selection', function () {
    $files = (new ModuleOptionApplier)->droppedFiles($this->dir, $this->schema, ['auth' => 'sanctum']);

    sort($files);
    expect($files)->toBe([
        'Console/Commands/EnableOAuthCommand.php',
        'OAuth/Layer.php',
        'resources/views/oauth/authorize.blade.php',
    ]);
});

test('droppedFiles is empty for a choice with no drops', function () {
    expect((new ModuleOptionApplier)->droppedFiles($this->dir, $this->schema, ['auth' => 'sanctum+oauth']))->toBe([]);
});

test('switching away from a choice retires the env keys only it declared', function () {
    // The accident: switching SmsMessaging from driver=twilio back to the log
    // driver left TWILIO_* sitting in .env. Harmless for a log driver; not
    // harmless for an auth or billing variant, where a stale key still RESOLVES
    // and something still reads it.
    (new ModuleOptionApplier)->apply($this->dir, $this->schema, ['auth' => 'sanctum+oauth'], $this->env);

    expect(File::get($this->env))->toContain('AUTH_OAUTH_ENABLED=true');

    (new ModuleOptionApplier)->apply(
        $this->dir,
        $this->schema,
        ['auth' => 'sanctum'],
        $this->env,
        ['auth' => 'sanctum+oauth'],   // what we are switching AWAY from
    );

    $contents = File::get($this->env);

    expect($contents)->toContain('# AUTH_OAUTH_ENABLED=true')
        ->and($contents)->not->toMatch('/^AUTH_OAUTH_ENABLED=/m');
});

test('a retired key is commented, never deleted', function () {
    // The value may be a credential the developer pasted in. A tool that
    // silently destroys one is worse than the stale key it is solving.
    // Commenting stops it resolving — the actual hazard — and leaves it
    // recoverable by eye.
    File::put($this->env, "APP_NAME=Test\nAUTH_OAUTH_ENABLED=secret-value-worth-keeping\n");

    (new ModuleOptionApplier)->apply(
        $this->dir,
        $this->schema,
        ['auth' => 'sanctum'],
        $this->env,
        ['auth' => 'sanctum+oauth'],
    );

    expect(File::get($this->env))->toContain('secret-value-worth-keeping');
});

test('a key the incoming choice also declares is left alone', function () {
    // EXISTING is declared by sanctum+oauth. If the incoming choice declared it
    // too, retiring it would comment out a key that is about to be written —
    // the ordering would decide the outcome, which is not a thing to leave to
    // ordering.
    $schema                                      = $this->schema;
    $schema['auth']['choices']['sanctum']['env'] = ['EXISTING' => 'kept'];

    (new ModuleOptionApplier)->apply(
        $this->dir,
        $schema,
        ['auth' => 'sanctum'],
        $this->env,
        ['auth' => 'sanctum+oauth'],
    );

    $contents = File::get($this->env);

    expect($contents)->toMatch('/^EXISTING=kept$/m')
        ->and($contents)->not->toContain('# EXISTING=');
});

test('a fresh install retires nothing', function () {
    // module:add passes no previous selection, so the retirement pass must be a
    // no-op there rather than commenting out keys the user already had.
    File::put($this->env, "APP_NAME=Test\nAUTH_OAUTH_ENABLED=set-by-hand\n");

    (new ModuleOptionApplier)->apply($this->dir, $this->schema, ['auth' => 'sanctum'], $this->env);

    expect(File::get($this->env))->toMatch('/^AUTH_OAUTH_ENABLED=set-by-hand$/m');
});
