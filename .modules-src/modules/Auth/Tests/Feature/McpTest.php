<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Auth\Mcp\AppServer;
use Modules\Auth\Mcp\Tools\WhoAmITool;

test('whoami tool returns the acting user', function () {
    $user = User::factory()->create(['email' => 'mcp@example.com']);

    AppServer::actingAs($user)
        ->tool(WhoAmITool::class)
        ->assertOk()
        ->assertSee('mcp@example.com');
});

test('the mcp http endpoint rejects unauthenticated requests', function () {
    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'ping',
    ])->assertUnauthorized();
});

test('the mcp http endpoint initializes for a valid sanctum token', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('mcp')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => [
                'protocolVersion' => '2025-06-18',
                'capabilities'    => [],
                'clientInfo'      => ['name' => 'pest', 'version' => '1.0'],
            ],
        ]);

    $response->assertOk();
    expect($response->json('result.serverInfo.name'))->toBe('Laravel Vue Template');
});

test('the mcp http endpoint lists and calls tools for a valid token', function () {
    $user    = User::factory()->create(['email' => 'caller@example.com']);
    $token   = $user->createToken('mcp')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->withHeaders($headers)->postJson('/mcp', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
    ])->assertOk()->assertSee('who-am-i');

    $call = $this->withHeaders($headers)->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id'      => 2,
        'method'  => 'tools/call',
        'params'  => ['name' => 'who-am-i', 'arguments' => []],
    ]);

    $call->assertOk()->assertSee('caller@example.com');
});
