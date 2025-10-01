<?php

declare(strict_types=1);

namespace App\Console\Commands\Vue;

use Illuminate\Console\Command;

use function Laravel\Prompts\text;

class MakePageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vue:make-page';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Vue page component.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = text(
            'What is the name of the new page component?',
            'E.g. HomePage',
            required: true,
            validate: function (string $value): ?string {
                return match (true) {
                    str($value)->endsWith('.vue')                     => 'Please provide the name without the .vue extension.',
                    file_exists(resource_path("ts/pages/$value.vue")) => 'Page already exists.',
                    default                                           => null,
                };
            }
        );

        $content = file_get_contents(base_path('/stubs/vue-make-page.stub'));
        $content = str($content)->replace('{{ name }}', $name);

        file_put_contents(resource_path("ts/pages/$name.vue"), $content);

        $this->components->info("Page $name created successfully.");

        return self::SUCCESS;
    }
}
