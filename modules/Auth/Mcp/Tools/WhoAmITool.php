<?php

declare(strict_types=1);

namespace Modules\Auth\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

// Reference tool + smoke test that the endpoint's auth actually resolves a
// user. Copy its shape; delete it once a project has real tools. It proves the
// one guarantee the server makes: $request->user() is the caller.
#[Name('who-am-i')]
#[Title('Who am I')]
#[Description('Return the authenticated user this MCP session is acting as.')]
class WhoAmITool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        return Response::json([
            'id'    => $user->id,
            'name'  => $user->full_name,
            'email' => $user->email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
