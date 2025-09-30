<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;
use Scrumble\TypeGenerator\Console\Commands\GenerateTypesCommand;
use Throwable;

class MigrateCommand extends BaseMigrateCommand
{
    public function __construct()
    {
        $migrator   = app('migrator');
        $dispatcher = app(Dispatcher::class);

        parent::__construct($migrator, $dispatcher);
    }

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(): int
    {
        $response = parent::handle();

        if ($response !== self::SUCCESS) {
            return $response;
        }

        if (app()->environment('local')) {
            $this->call(GenerateTypesCommand::class, ['--outputDir' => 'resources/ts/types/models', '--namespace' => true]);
        }

        return self::SUCCESS;
    }
}
