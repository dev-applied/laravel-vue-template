<?php

declare(strict_types=1);

namespace Modules\SmsMessaging;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\SmsMessaging\Contracts\SmsSender;
use Modules\SmsMessaging\Drivers\LogSmsSender;
use Modules\SmsMessaging\Drivers\TwilioSmsSender;
use Modules\SmsMessaging\Notifications\SmsChannel;
use Modules\SmsMessaging\Otp\SmsOtpChannel;
use Modules\SmsMessaging\Support\SmsManager;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/sms.php', 'sms');

        $this->app->singleton(SmsSender::class, function ($app) {
            // `log` is the default and stays the default: the module has to
            // install and its tests have to run with no account, no credentials
            // and no network. A project that has not configured a vendor yet
            // still gets opt-out handling and a delivery log, which is most of
            // the value.
            return match (config('sms.driver', 'log')) {
                'twilio' => class_exists(TwilioSmsSender::class)
                    ? new TwilioSmsSender(
                        (string) config('sms.twilio.sid', ''),
                        (string) config('sms.twilio.token', ''),
                        (string) config('sms.twilio.from', ''),
                    )
                    : new LogSmsSender,
                default => new LogSmsSender,
            };
        });

        $this->app->singleton(SmsManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/Routes/api.php');

        if (is_file(__DIR__.'/Routes/webhook.php')) {
            Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/Routes/webhook.php');
        }

        // `sms` becomes a notification channel like `mail`.
        $this->app->make(\Illuminate\Notifications\ChannelManager::class)
            ->extend('sms', fn ($app) => $app->make(SmsChannel::class));

        // Bind Otp's declared SMS seam, but only when Otp is actually installed
        // — the binding names a contract that ships with that module, and
        // registering it unconditionally fatals the day somebody removes it.
        if (is_dir(base_path('modules/Otp'))) {
            $this->app->bind('otp.channel.sms', SmsOtpChannel::class);
        }
    }
}
