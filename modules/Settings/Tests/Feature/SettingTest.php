<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Modules\Settings\Http\Controllers\SettingController;
use Modules\Settings\Models\Setting;
use Modules\Settings\Support\SettingDefinition;
use Modules\Settings\Support\SettingRegistry;
use Modules\Settings\Support\SettingsManager;

beforeEach(function () {
    $this->user = User::factory()->create();
    Gate::define('manage-settings', fn () => true);

    $this->registry = app(SettingRegistry::class);
    $this->settings = app(SettingsManager::class);

    $this->registry->add('site.name', 'Site name', ['default' => 'Acme', 'public' => true]);
    $this->registry->add('features.beta', 'Beta features', ['type' => 'boolean', 'default' => false]);
    $this->registry->add('limits.per_page', 'Rows per page', ['type' => 'integer', 'default' => 25]);
    $this->registry->add('stripe.key', 'Stripe secret key', ['secret' => true, 'group' => 'Billing']);
});

test('an unset setting falls back to its declared default', function () {
    // A fresh install should behave like a configured one, not a broken one.
    expect($this->settings->get('site.name'))->toBe('Acme')
        ->and($this->settings->get('features.beta'))->toBeFalse()
        ->and($this->settings->get('limits.per_page'))->toBe(25);
});

test('a stored value wins over the default', function () {
    $this->settings->set('site.name', 'Widgets Inc');

    expect($this->settings->get('site.name'))->toBe('Widgets Inc');
});

test('values keep their declared type', function () {
    // JSON storage plus a declared type, so a bool stays a bool rather than
    // coming back as the string "1".
    $this->settings->set('features.beta', true);
    $this->settings->set('limits.per_page', 50);

    expect($this->settings->get('features.beta'))->toBeTrue()
        ->and($this->settings->get('limits.per_page'))->toBe(50);
});

test('the helper reads through the manager', function () {
    $this->settings->set('site.name', 'Via helper');

    expect(setting('site.name'))->toBe('Via helper')
        ->and(setting('nope', 'fallback'))->toBe('fallback');
});

test('a write invalidates the cache', function () {
    // The classic bug: settings cached forever and a save that does not clear.
    expect($this->settings->get('site.name'))->toBe('Acme');

    $this->settings->set('site.name', 'Changed');

    expect($this->settings->get('site.name'))->toBe('Changed');
});

test('the management payload groups and includes current values', function () {
    $this->settings->set('site.name', 'Widgets Inc');

    $response = $this->actingAs($this->user)->getJson('/api/v1/settings')->assertOk();

    $groups = collect($response->json('groups'));

    // Groups are sorted so the screen does not reshuffle between deploys.
    expect($groups->pluck('group')->all())->toBe(['Billing', 'General']);

    $general = $groups->firstWhere('group', 'General')['settings'];
    expect(collect($general)->firstWhere('key', 'site.name')['value'])->toBe('Widgets Inc');
});

test('a secret value never leaves the server', function () {
    $this->settings->set('stripe.key', 'sk_live_realkey');

    $response = $this->actingAs($this->user)->getJson('/api/v1/settings')->assertOk();

    $billing = collect($response->json('groups'))->firstWhere('group', 'Billing')['settings'];
    $stripe  = collect($billing)->firstWhere('key', 'stripe.key');

    expect($stripe['value'])->toBe(SettingController::MASK)
        ->and($response->content())->not->toContain('sk_live_realkey');
});

test('an unset secret reads as null, not as the mask', function () {
    // So the UI can say "not configured" rather than implying one is set.
    $response = $this->actingAs($this->user)->getJson('/api/v1/settings');

    $billing = collect($response->json('groups'))->firstWhere('group', 'Billing')['settings'];

    expect(collect($billing)->firstWhere('key', 'stripe.key')['value'])->toBeNull();
});

test('saving a masked secret unchanged leaves the real value alone', function () {
    // The UI round-trips what it was given. Without this, opening the settings
    // screen and pressing Save would overwrite every credential with asterisks.
    $this->settings->set('stripe.key', 'sk_live_realkey');

    $this->actingAs($this->user)
        ->putJson('/api/v1/settings', ['settings' => ['stripe.key' => SettingController::MASK]])
        ->assertOk();

    expect($this->settings->get('stripe.key'))->toBe('sk_live_realkey');
});

test('a secret can still be replaced', function () {
    $this->settings->set('stripe.key', 'sk_live_old');

    $this->actingAs($this->user)
        ->putJson('/api/v1/settings', ['settings' => ['stripe.key' => 'sk_live_new']])
        ->assertOk();

    expect($this->settings->get('stripe.key'))->toBe('sk_live_new');
});

test('an undeclared key is rejected rather than silently ignored', function () {
    // Ignoring it would make a typo look saved.
    $this->actingAs($this->user)
        ->putJson('/api/v1/settings', ['settings' => ['made.up' => 'x']])
        ->assertStatus(422);

    expect(Setting::where('key', 'made.up')->exists())->toBeFalse();
});

test('a value that does not match its declared type is rejected', function () {
    $this->actingAs($this->user)
        ->putJson('/api/v1/settings', ['settings' => ['limits.per_page' => 'not a number']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('settings.limits.per_page');
});

test('a select only accepts its declared choices', function () {
    $this->registry->add('theme', 'Theme', [
        'type'    => SettingDefinition::TYPE_SELECT,
        'choices' => ['light' => 'Light', 'dark' => 'Dark'],
    ]);

    $this->actingAs($this->user)
        ->putJson('/api/v1/settings', ['settings' => ['theme' => 'neon']])
        ->assertStatus(422);

    $this->actingAs($this->user)
        ->putJson('/api/v1/settings', ['settings' => ['theme' => 'dark']])
        ->assertOk();
});

test('project rules stack on top of the type rules', function () {
    $this->registry->add('support.email', 'Support address', ['rules' => ['email']]);

    $this->actingAs($this->user)
        ->putJson('/api/v1/settings', ['settings' => ['support.email' => 'not-an-email']])
        ->assertStatus(422);
});

test('saving several settings writes them all', function () {
    $this->actingAs($this->user)
        ->putJson('/api/v1/settings', ['settings' => [
            'site.name'       => 'Bulk',
            'features.beta'   => true,
            'limits.per_page' => 100,
        ]])
        ->assertOk();

    expect($this->settings->get('site.name'))->toBe('Bulk')
        ->and($this->settings->get('features.beta'))->toBeTrue()
        ->and($this->settings->get('limits.per_page'))->toBe(100);
});

test('an empty payload is rejected', function () {
    $this->actingAs($this->user)
        ->putJson('/api/v1/settings', ['settings' => []])
        ->assertStatus(422);
});

test('public settings are readable signed out', function () {
    // The app name is needed before anyone has logged in.
    $this->settings->set('site.name', 'Public Co');

    // Plain array access rather than a dotted json path: the KEY contains a
    // dot, which assertJsonPath would read as nesting.
    $settings = $this->getJson('/api/v1/settings/public')->assertOk()->json('settings');

    expect($settings['site.name'])->toBe('Public Co');
});

test('a non-public setting is absent from the public endpoint', function () {
    $this->settings->set('features.beta', true);

    $settings = $this->getJson('/api/v1/settings/public')->assertOk()->json('settings');

    expect($settings)->not->toHaveKey('features.beta');
});

test('a secret marked public is still withheld', function () {
    // Declaring both is a mistake, and the safe reading of it is "secret".
    $this->registry->add('leaky', 'Leaky', ['public' => true, 'secret' => true]);
    $this->settings->set('leaky', 'sk_live_oops');

    $response = $this->getJson('/api/v1/settings/public')->assertOk();

    expect($response->content())->not->toContain('sk_live_oops');
});

test('the management endpoints are gated', function () {
    Gate::define('manage-settings', fn () => false);

    $this->actingAs($this->user)->getJson('/api/v1/settings')->assertForbidden();
    $this->actingAs($this->user)->putJson('/api/v1/settings', ['settings' => []])->assertForbidden();
});

test('the management endpoints require authentication', function () {
    $this->getJson('/api/v1/settings')->assertUnauthorized();
});

test('allDeclared excludes secrets by default', function () {
    $this->settings->set('stripe.key', 'sk_live_realkey');

    expect($this->settings->allDeclared())->not->toHaveKey('stripe.key')
        ->and($this->settings->allDeclared(includeSecrets: true))->toHaveKey('stripe.key');
});

test('a long-lived worker does not keep serving the settings it booted with', function () {
    // SettingsManager memoises the whole map in a property, and it was bound as
    // a SINGLETON. In a web request that is harmless — the container dies with
    // the request. In a queue worker the container lives for days, so a setting
    // changed through the admin UI never reached the worker: Cache::forget on
    // the write path clears the SHARED cache for every process, but it cannot
    // reach into another process's property.
    //
    // scoped() is the fix, and this test is what tells the two bindings apart:
    // Laravel resets scoped instances between queue jobs, and does not reset
    // singletons.
    $manager = app(SettingsManager::class);
    $manager->set('site_name', 'Before');

    expect($manager->get('site_name'))->toBe('Before');

    // Another process writes and flushes the shared cache. This instance's
    // memo is untouched, which is the memo doing its job.
    Setting::query()->updateOrCreate(['key' => 'site_name'], ['value' => 'After']);
    Cache::forget('settings:all');

    expect($manager->get('site_name'))->toBe('Before');

    // The next job gets a fresh instance and sees the new value.
    app()->forgetScopedInstances();

    expect(app(SettingsManager::class)->get('site_name'))->toBe('After');
});

test('the settings cache is bounded, so one missed invalidation is not permanent', function () {
    // Every write calls flush(), so a TTL should never be what refreshes this.
    // It is here because rememberForever turns a single failed forget — a
    // dropped delete on one cache node — into a permanently wrong answer with
    // no way back short of clearing the cache by hand.
    //
    // Asserted on the call rather than by waiting a day: rememberForever() and
    // remember($key, $ttl) are different methods, so this goes red the moment
    // someone puts the old one back.
    Cache::shouldReceive('remember')
        ->once()
        ->withArgs(fn (string $key, int $ttl) => $key === 'settings:all' && $ttl > 0)
        ->andReturn(['site_name' => 'Whatever']);

    expect(app(SettingsManager::class)->get('site_name'))->toBe('Whatever');
});
