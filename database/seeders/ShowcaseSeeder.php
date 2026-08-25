<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Fills a demo install with enough content that every installed module has
 * something to SHOW.
 *
 * A bare `migrate:fresh` leaves every registry-driven surface empty, which is
 * exactly the state the empty-state work of 2026-08-25 was about: correct, and
 * useless for demonstrating anything. This is the opposite tool — it exists so
 * a reviewer can open the deployed site and see each module doing its job.
 *
 * Every block is guarded on its table existing, because the module set is a
 * per-project choice: a project that never installed Booking should not have
 * this fail, it should simply seed less.
 *
 * Run with:  php artisan db:seed --class=ShowcaseSeeder
 */
class ShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoSeeder::class);

        $admin = User::where('email', 'admin@example.test')->first();
        $staff = User::where('email', 'staff@example.test')->first();
        $user  = User::where('email', 'user@example.test')->first();

        if ($admin === null) {
            return;
        }

        $this->tags();
        $this->tasks($admin, $staff, $user);
        $this->support($admin, $user);
        $this->announcements();
        $this->booking();
        $this->settings();
    }

    private function tags(): void
    {
        if (! Schema::hasTable('tags')) {
            return;
        }

        foreach ([
            ['Urgent', 'urgent', 'error'],
            ['Billing', 'billing', 'warning'],
            ['Onboarding', 'onboarding', 'info'],
            ['Feature request', 'feature-request', 'success'],
        ] as [$name, $slug, $colour]) {
            \Modules\Tags\Models\Tag::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'color' => $colour, 'type' => 'general'],
            );
        }
    }

    private function tasks(User $admin, ?User $staff, ?User $user): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        $rows = [
            ['Review the Q3 launch checklist', 'in_progress', 'high',   $admin->getKey(),   now()->addDays(2)],
            ['Chase the outstanding invoice',  'todo',        'urgent', $staff?->getKey(),  now()->addDay()],
            ['Write the release notes',        'todo',        'normal', $staff?->getKey(),  now()->addDays(5)],
            ['Archive last quarter exports',   'done',        'low',    $user?->getKey(),   now()->subDays(3)],
            ['Book the all-hands room',        'todo',        'normal', null,               now()->addDays(9)],
        ];

        foreach ($rows as $i => [$title, $status, $priority, $assignee, $due]) {
            \Modules\Tasks\Models\Task::updateOrCreate(
                ['title' => $title],
                [
                    'status'       => $status,
                    'priority'     => $priority,
                    'assigned_to'  => $assignee,
                    'due_at'       => $due,
                    'completed_at' => $status === 'done' ? now()->subDays(3) : null,
                    'position'     => $i,
                ],
            );
        }
    }

    private function support(User $admin, ?User $user): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        $rows = [
            ['Cannot upload a PDF over 10MB', 'open',        'high',   'Priya Raman',  'priya@example.test'],
            ['Invoice shows the wrong VAT',   'in_progress', 'urgent', 'Tom Okafor',   'tom@example.test'],
            ['How do I export my data?',      'resolved',    'normal', 'Lena Fischer', 'lena@example.test'],
        ];

        foreach ($rows as $i => [$subject, $status, $priority, $name, $email]) {
            \Modules\Support\Models\SupportTicket::updateOrCreate(
                ['subject' => $subject],
                [
                    'name'        => $name,
                    'email'       => $email,
                    'body'        => "Demo ticket seeded for the module showcase.\n\n{$subject}",
                    'status'      => $status,
                    'priority'    => $priority,
                    'user_id'     => $i === 2 ? $user?->getKey() : null,
                    'assigned_to' => $status === 'open' ? null : $admin->getKey(),
                    'resolved_at' => $status === 'resolved' ? now()->subDay() : null,
                ],
            );
        }
    }

    private function announcements(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        $rows = [
            ['Scheduled maintenance this Sunday', 'info',    false],
            ['New: export your data as CSV',      'success', false],
            ['Action needed: confirm your email', 'warning', true],
        ];

        foreach ($rows as $i => [$title, $level, $ack]) {
            \Modules\Announcements\Models\Announcement::updateOrCreate(
                ['title' => $title],
                [
                    'body'                     => 'Seeded for the module showcase so the banner has something to display.',
                    'level'                    => $level,
                    'placement'                => 'banner',
                    'audience'                 => 'all',
                    'dismissible'              => ! $ack,
                    'requires_acknowledgement' => $ack,
                    'published_at'             => now()->subDays($i + 1),
                    'starts_at'                => now()->subDays($i + 1),
                ],
            );
        }
    }

    private function booking(): void
    {
        if (! Schema::hasTable('bookable_resources')) {
            return;
        }

        foreach ([
            ['Main meeting room', 'main-meeting-room', 12],
            ['Focus booth',       'focus-booth',        1],
        ] as [$name, $slug, $capacity]) {
            \Modules\Booking\Models\BookableResource::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'capacity' => $capacity, 'is_active' => true],
            );
        }
    }

    private function settings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        // Values only — the KEYS come from SettingRegistry::add() in a service
        // provider, so seeding a value for a key nothing registered would be
        // invisible in the UI. Guarded so this is a no-op until a project
        // registers something.
        foreach (\Modules\Settings\Models\Setting::all() as $setting) {
            if (blank($setting->value)) {
                $setting->update(['value' => 'Demo value']);
            }
        }
    }
}
