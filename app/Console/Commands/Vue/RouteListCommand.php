<?php

declare(strict_types=1);

namespace App\Console\Commands\Vue;

use Illuminate\Console\Command;

use function Laravel\Prompts\table;

class RouteListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vue:route-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all routes (path, file, name) parsed from resources/ts/router/paths.ts.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = base_path('resources/ts/router/paths.ts');

        if (! file_exists($path)) {
            $this->components->error('The routes file does not exist.');

            return self::FAILURE;
        }

        if (($content = file_get_contents($path)) === false) {
            $this->components->error('Could not read the routes file.');

            return self::FAILURE;
        }

        $map = [];

        if (preg_match('/export\s+const\s+ROUTES\s*=\s*{(?P<body>[^}]+)}/s', $content, $routes)) {
            $body = $routes['body'];

            if (preg_match_all('/^\s*([A-Z0-9_]+)\s*:\s*["\']([^"\']+)["\']\s*,?\s*$/m', $body, $routePairs, PREG_SET_ORDER)) {
                foreach ($routePairs as $pair) {
                    $map[$pair[1]] = $pair[2];
                }
            }
        }

        $rows    = [];
        $pattern = '/RouteDesigner\\.route\(\s*["\']([^"\']+)["\']\s*,\s*["\']([^"\']+)["\']\s*,\s*ROUTES\\.([A-Z0-9_]+)\s*\)/';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $path = $match[1];
                $file = $match[2];
                $key  = $match[3];
                $name = $map[$key] ?? ('<undefined: '.$key.'>');

                $rows[] = [$path, $file, $name];
            }
        }

        if (empty($rows)) {
            $this->components->error('No routes were found using RouteDesigner.route(...)');

            return self::FAILURE;
        }

        $this->components->info('Found '.count($rows).' routes.');

        table(['Path', 'File', 'Name'], $rows);

        return self::SUCCESS;
    }
}
