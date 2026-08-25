<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;

// These exercise the optional OAuth layer. They must run with the layer's env
// on regardless of the ambient .env, so each test forces the config and the
// module's route/guard wiring for the request lifecycle.
beforeEach(function () {
    config()->set('auth.oauth.enabled', true);
});

function pkcePair(): array
{
    $verifier  = Str::random(64);
    $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

test('discovery documents advertise the passport endpoints', function () {
    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('registration_endpoint', url('oauth/register'))
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token']);

    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonPath('scopes_supported', ['mcp:use']);
});

test('dynamic client registration creates a public auth-code client', function () {
    $response = $this->postJson('/oauth/register', [
        'client_name'   => 'Test MCP Client',
        'redirect_uris' => ['https://client.example/callback'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('token_endpoint_auth_method', 'none')
        ->assertJsonPath('scope', 'mcp:use');

    expect($response->json('client_id'))->not->toBeEmpty();
});

test('full auth-code + PKCE flow issues a token that reaches /mcp', function () {
    $user = User::factory()->create(['email' => 'oauth@example.com']);

    // 1. Dynamic client registration (what an MCP client does on first connect).
    $clientId = $this->postJson('/oauth/register', [
        'client_name'   => 'Flow Client',
        'redirect_uris' => ['https://client.example/callback'],
    ])->assertCreated()->json('client_id');

    [$verifier, $challenge] = pkcePair();
    $state                  = Str::random(16);

    // 2. The user is logged into the web session (the SPA login does this when
    //    OAuth is on). Hit /oauth/authorize → consent screen, authToken in session.
    $this->actingAs($user, 'web')->get('/oauth/authorize?'.http_build_query([
        'client_id'             => $clientId,
        'redirect_uri'          => 'https://client.example/callback',
        'response_type'         => 'code',
        'scope'                 => 'mcp:use',
        'state'                 => $state,
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertOk()->assertSee('Authorization Request');

    $authToken = session('authToken');
    expect($authToken)->not->toBeEmpty();

    // 3. Approve → redirect back to the client callback carrying ?code=.
    $approve = $this->actingAs($user, 'web')->post('/oauth/authorize', [
        'auth_token' => $authToken,
        'state'      => $state,
        'client_id'  => $clientId,
        'scope'      => 'mcp:use',
    ]);
    $approve->assertRedirect();

    parse_str((string) parse_url($approve->headers->get('Location'), PHP_URL_QUERY), $cb);
    expect($cb['state'])->toBe($state);
    expect($cb['code'] ?? null)->not->toBeEmpty();

    // 4. Exchange the code (+ PKCE verifier) for an access token.
    $token = $this->post('/oauth/token', [
        'grant_type'    => 'authorization_code',
        'client_id'     => $clientId,
        'redirect_uri'  => 'https://client.example/callback',
        'code_verifier' => $verifier,
        'code'          => $cb['code'],
    ]);
    $token->assertOk();
    $accessToken = $token->json('access_token');
    expect($accessToken)->not->toBeEmpty();

    // 5. The OAuth token authenticates against /mcp via the `api` guard.
    $this->withHeaders(['Authorization' => "Bearer {$accessToken}"])
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'tools/call',
            'params'  => ['name' => 'who-am-i', 'arguments' => []],
        ])
        ->assertOk()
        ->assertSee('oauth@example.com');
});
