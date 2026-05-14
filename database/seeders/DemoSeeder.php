<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the bare minimum of demo / QA login accounts so a QA tester (or
 * AI agent) can log in immediately after `php artisan migrate:fresh`
 * without manual `user:create` invocations.
 *
 * Run with:  php artisan db:seed --class=DemoSeeder
 *
 * NOT registered in DatabaseSeeder by default — opt-in per project so
 * production seeding stays predictable. Most projects will want to
 * replace these with role-tiered users (admin / staff / user) once a
 * permission system is wired up.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['first_name' => 'Demo',  'last_name' => 'Admin',  'email' => 'admin@example.test'],
            ['first_name' => 'Demo',  'last_name' => 'Staff',  'email' => 'staff@example.test'],
            ['first_name' => 'Demo',  'last_name' => 'User',   'email' => 'user@example.test'],
        ];

        foreach ($accounts as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data + ['password' => 'password'],
            );
        }
    }
}
