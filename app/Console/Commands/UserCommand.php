<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Faker\Generator;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class UserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:user {email?} {--password=} {--firstName=} {--lastName=}';

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
        $validate = function (string $value): ?string {
            if (filled($value) && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'Please enter a valid email address.';
            }

            if (filled($value) && User::where('email', $value)->exists()) {
                return 'This email is already in use.';
            }

            return null;
        };

        $email     = text('Email', default: $this->argument('email') ?? $faker->email, validate: $validate);
        $firstName = text('First Name', default: $this->option('firstName') ?: $faker->firstName);
        $lastName  = text('Last Name', default: $this->option('lastName') ?: $faker->lastName);
        $password  = text('Password', default: $this->option('password') ?: 'Test123!');

        $user             = new User;
        $user->email      = $email;
        $user->first_name = $firstName;
        $user->last_name  = $lastName;
        $user->password   = $password;

        $user->save();

        $this->components->info('User created successfully');

        table(['Email', 'Password'], [[$user->email, $password]]);

        return self::SUCCESS;
    }
}
