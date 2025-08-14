<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Faker\Generator;
use Illuminate\Console\Command;

class UserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:user {email?} {--password=} {--firstName=} {--lastName=} {--role=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a user';

    /**
     * Execute the console command.
     */
    public function handle(Generator $faker): int
    {
        $user             = new User;
        $user->email      = $this->argument('email') ?? $faker->email;
        $user->first_name = $this->option('firstName') ?: $faker->firstName;
        $user->last_name  = $this->option('lastName') ?: $faker->lastName;
        $password         = $this->option('password') ?: 'Test123!';
        $user->password   = $password;

        if ($this->option('role')) {
            $user->assignRole($this->option('role'));
        }
        //        else {
        //            $user->assignRole(DEFAULT_ROLE);
        //        }

        $user->save();

        $this->output->success('Successfully created user');
        $this->output->table(['Email', 'Password'], [
            [$user->email, $password],
        ]);

        return Command::SUCCESS;
    }
}
