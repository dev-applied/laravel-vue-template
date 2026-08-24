---
description: Install / configure Laravel Boost so AI agents can use its MCP tools (DB introspection, route inspection, log tailing, etc.)
allowed-tools: Bash
---

Laravel Boost is in `composer.json` (`require-dev`) but the per-project install step (writes `.mcp.json` and publishes config) has to be run manually.

1. Make sure dependencies are installed: `docker compose exec webserver composer install`
2. Run the Boost installer interactively (it asks which MCP tools to enable):

   ```sh
   docker compose exec -it webserver php artisan boost:install
   ```

3. After the installer finishes, commit:
   - `.mcp.json` at the project root (Boost's MCP server registration)
   - `config/boost.php` if published
4. Restart Claude Code in this project so it picks up the new MCP server.

Boost exposes tools like `mcp__boost__schema` (DB schema), `mcp__boost__routes` (Laravel routes), `mcp__boost__logs` (tail), and `mcp__boost__artisan` (run any artisan command) directly to the agent — dramatically faster than grep-and-guess across `routes/` and `database/migrations/`.

Reference: https://laravel.com/docs/boost
