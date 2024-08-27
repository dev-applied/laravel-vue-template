<?php

namespace App\Console\Commands;

use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Based\TypeScript\Commands\TypeScriptGenerateCommand;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;
use Throwable;

class MigrateCommand extends BaseMigrateCommand
{


    public function __construct()
    {
        $migrator = app("migrator");
        $dispatcher = app(Dispatcher::class);

        parent::__construct($migrator, $dispatcher);
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws Throwable
     */
    public function handle(): int
    {
        $response = parent::handle();
        if ($response !== self::SUCCESS) {
            return $response;
        }
        if (app()->environment('local')) {
            $this->call(ModelsCommand::class, ['-W' => true, '-R' => true, '-p' => true]);
            $this->call(TypeScriptGenerateCommand::class);
        }

        return self::SUCCESS;
    }
}
