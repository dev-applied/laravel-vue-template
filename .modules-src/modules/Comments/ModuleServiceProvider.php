<?php

declare(strict_types=1);

namespace Modules\Comments;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Comments\Support\CommentableRegistry;
use Modules\Comments\Support\MentionParser;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommentableRegistry::class);
        $this->app->singleton(MentionParser::class);

        config()->set('comments.threading', env('COMMENTS_THREADING', false));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');
    }
}
