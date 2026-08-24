<?php

declare(strict_types=1);

use App\Support\Modules\ModuleOptionResolver;

function authSchema(): array
{
    return [
        'auth' => [
            'type'    => 'select',
            'default' => 'sanctum',
            'choices' => [
                'sanctum'       => ['label' => 'Sanctum only'],
                'sanctum+oauth' => ['label' => 'Sanctum + OAuth'],
            ],
        ],
    ];
}

test('non-interactive resolve falls back to the default', function () {
    $r = new ModuleOptionResolver;

    expect($r->resolve(authSchema(), [], null))->toBe(['auth' => 'sanctum']);
});

test('a flag overrides the default', function () {
    $r = new ModuleOptionResolver;

    expect($r->resolve(authSchema(), ['auth=sanctum+oauth'], null))->toBe(['auth' => 'sanctum+oauth']);
});

test('an unknown choice is rejected', function () {
    $r = new ModuleOptionResolver;

    $r->resolve(authSchema(), ['auth=nope'], null);
})->throws(InvalidArgumentException::class);

test('multiselect parses a comma list and confirm coerces truthy strings', function () {
    $schema = [
        'channels' => [
            'type'    => 'multiselect',
            'choices' => ['sms' => [], 'email' => [], 'whatsapp' => []],
        ],
        'debug' => ['type' => 'confirm', 'default' => false],
    ];

    $r = new ModuleOptionResolver;

    expect($r->resolve($schema, ['channels=sms,email', 'debug=yes'], null))
        ->toBe(['channels' => ['sms', 'email'], 'debug' => true]);
});

test('the prompter is used when no flag is given', function () {
    $r = new ModuleOptionResolver;

    $resolved = $r->resolve(authSchema(), [], fn (string $key, array $def) => 'sanctum+oauth');

    expect($resolved)->toBe(['auth' => 'sanctum+oauth']);
});
