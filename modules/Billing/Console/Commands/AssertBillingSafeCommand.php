<?php

declare(strict_types=1);

namespace Modules\Billing\Console\Commands;

use Illuminate\Console\Command;

/**
 * The pre-deploy guard.
 *
 * The sandbox flag is the single most dangerous item in a billing build: while
 * it is on, anyone running a sandbox-capable build can self-grant a paid tier.
 * It gets switched on deliberately for testing and NOTHING turns it off again.
 * A human remembering is not a control; a failing pipeline step is.
 *
 *   php artisan billing:assert-safe
 */
class AssertBillingSafeCommand extends Command
{
    // NOT --env: Laravel reserves that as a global option and registering it
    // again throws before the command ever runs.
    protected $signature = 'billing:assert-safe {--target=production : The environment being deployed to}';

    protected $description = 'Fail if the billing configuration is unsafe for the target environment';

    public function handle(): int
    {
        $target   = $this->option('target');
        $problems = [];

        if ($target === 'production') {
            if (config('billing.allow_sandbox', false)) {
                $problems[] = 'BILLING_ALLOW_SANDBOX is on — sandbox purchases would grant real access.';
            }

            if (config('billing.allow_switcher', false)) {
                $problems[] = 'BILLING_ALLOW_SWITCHER is on — anyone could set their own tier.';
            }
        }

        // Missing keys are silent at runtime: the SDK simply never configures
        // and every purchase path quietly does nothing. Failing the build is
        // the only reliable defence, because the symptom is silence.
        foreach (['webhook_secret' => 'REVENUECAT_WEBHOOK_SECRET'] as $key => $env) {
            if ((string) config("billing.$key", '') === '') {
                $problems[] = "$env is not set — every webhook will be rejected and nobody will ever be granted access.";
            }
        }

        foreach ($problems as $problem) {
            $this->error('✖ '.$problem);
        }

        if ($problems !== []) {
            return self::FAILURE;
        }

        $this->info('✓ Billing configuration is safe for '.$target.'.');

        return self::SUCCESS;
    }
}
