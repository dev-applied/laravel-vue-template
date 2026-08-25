<?php

declare(strict_types=1);

namespace Modules\Auth\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Modules\Auth\Mcp\Tools\WhoAmITool;

// The template's default MCP server. Every request is authenticated — the
// route (registered in ModuleServiceProvider) carries `auth:sanctum` by
// default, and `auth:sanctum,api` once the OAuth layer is enabled, so a tool
// can always trust $request->user(). Projects add their own tools to $tools.
#[Name('Laravel Vue Template')]
#[Version('0.1.0')]
#[Instructions('MCP server for this application. Tools act as the authenticated user.')]
class AppServer extends Server
{
    protected array $tools = [
        WhoAmITool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
