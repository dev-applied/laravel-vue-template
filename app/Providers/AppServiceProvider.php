<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mixins\HasManyMixin;
use App\Mixins\VuetifyPaginateMixin;
use App\Mixins\WhoDidItMixin;
use App\Models\Item;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Modules\DataImport\Support\ImportRegistry;
use Modules\Exports\Support\ExportRegistry;
use Modules\GlobalSearch\Support\SearchRegistry;
use ReflectionException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $this->configureModels();

        $this->configureDatabase();

        $this->configureHttp();

        $this->configureMixins();

        $this->configureRateLimiting();

        $this->configurePasswords();

        $this->configureImports();
        $this->configureExports();
        $this->configureSearch();
    }

    /**
     * Wire the template's worked example into the DataImport module.
     *
     * The registry is an allow-list — an import writes to the database, so the
     * set of writable targets has to be something a developer deliberately
     * exposed, and the module ships with it empty on purpose. That left a
     * freshly-installed wizard offering an empty selector with nothing saying
     * why, which reads as a broken page rather than an unconfigured one.
     *
     * Guarded because the template ships WITHOUT the module: `module:add
     * DataImport` is what puts these classes on disk, and referencing them
     * unconditionally would fatal every project that never adds it.
     *
     * The directory check is not belt-and-braces, it is the actual guard.
     * `class_exists()` consults composer's classmap, which is a CACHE: remove
     * a module without dumping the autoloader and it still answers true, then
     * the include fatals. That failure is self-sealing — booting is what
     * `artisan module:add` does, so the one command that would repair it
     * cannot run either. Asking the filesystem cannot go stale.
     */
    public function configureImports(): void
    {
        if (! is_dir(base_path('modules/DataImport'))) {
            return;
        }

        if (! class_exists(ImportRegistry::class) || ! class_exists(Item::class)) {
            return;
        }

        app(ImportRegistry::class)->register(
            key: 'items',
            label: 'Items',
            fields: ['name' => 'Name', 'description' => 'Description'],
            rules: ['name' => 'required|string|max:255', 'description' => 'nullable|string'],
            required: ['name'],
            handler: fn (array $row) => Item::updateOrCreate(['name' => $row['name']], $row),
        );
    }

    /**
     * The Items example, as an EXPORT source.
     *
     * Exports shipped in the bundle with no target registered and no
     * `AppExportButton` anywhere, so its page said "Start one from any
     * listing's Export button" about a button that did not exist. DataImport
     * had exactly the same gap and was wired to Items; its twin was not.
     *
     * Guarded on the directory rather than the class: class_exists() reads
     * composer's CLASSMAP, which answers true for a module that has been
     * deleted and then fatals on include — and that failure is self-sealing,
     * because booting is what `module:add` does.
     */
    public function configureExports(): void
    {
        if (! is_dir(base_path('modules/Exports'))) {
            return;
        }

        if (! class_exists(ExportRegistry::class) || ! class_exists(Item::class)) {
            return;
        }

        app(ExportRegistry::class)->register(
            key: 'items',
            label: 'Items',
            columns: ['id' => 'ID', 'name' => 'Name', 'description' => 'Description', 'created_at' => 'Created'],
            query: fn (array $filters) => Item::query()
                ->when($filters['search'] ?? null, fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
                ->orderBy('id'),
        );
    }

    /**
     * The Items example, as SEARCH sources.
     *
     * Same guard and same reason as the two above: the registry is an
     * allow-list, so a freshly-installed palette finds nothing until a project
     * declares something, and a palette that always answers "nothing matched"
     * reads as broken rather than unconfigured. Exports shipped exactly that
     * way — a page pointing at a button that did not exist anywhere.
     *
     * Two sources rather than one, because grouping is the whole point of the
     * endpoint and a single source cannot demonstrate it.
     */
    public function configureSearch(): void
    {
        if (! is_dir(base_path('modules/GlobalSearch'))) {
            return;
        }

        if (! class_exists(SearchRegistry::class) || ! class_exists(Item::class)) {
            return;
        }

        $registry = app(SearchRegistry::class);

        $registry->register(
            key: 'items',
            label: 'Items',
            query: fn (string $term) => Item::query()
                ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%"))
                ->orderBy('name'),
            title: fn (Item $item) => $item->name,
            subtitle: fn (Item $item) => $item->description,
            route: fn (Item $item) => ['name' => 'items.edit', 'params' => ['id' => $item->id]],
            icon: 'inventory_2',
            order: 0,
        );

        $registry->register(
            key: 'users',
            label: 'People',
            query: fn (string $term) => User::query()
                ->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"))
                ->orderBy('first_name'),
            title: fn (User $user) => mb_trim("{$user->first_name} {$user->last_name}") ?: $user->email,
            subtitle: fn (User $user) => $user->email,
            icon: 'person',
            order: 10,
        );
    }

    public function configureModels(): void
    {
        Model::automaticallyEagerLoadRelationships();
    }

    public function configureDatabase(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    public function configureHttp(): void
    {
        Http::preventStrayRequests($this->app->runningUnitTests());
    }

    /**
     * @throws ReflectionException
     */
    public function configureMixins(): void
    {
        HasMany::mixin(new HasManyMixin);
        Relation::mixin(new VuetifyPaginateMixin);
        Builder::mixin(new VuetifyPaginateMixin);
        QueryBuilder::mixin(new VuetifyPaginateMixin);
        Blueprint::mixin(new WhoDidItMixin);
    }

    public function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(1000)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function configurePasswords(): void
    {
        if (! $this->app->isProduction()) {
            Password::defaults();

            return;
        }

        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });
    }

    // To enable Import both classes install laravel-notification-channels/twilio and call the function from the boot method
    private function configureTwilio(): void
    {
        if (! $this->app->environment('production')) {
            $this->app->extend(TwilioService::class, function () {
                return new TwilioHogService(
                    config('twilio-notification-channel.account_sid'),
                    config('twilio-notification-channel.auth_token'),
                    config('twilio-notification-channel.mock_server_url'),
                );
            });
        }
    }

    // To enable Import both classes install aws/aws-sdk-php and laravel-notification-channels/aws-sns and call the function from the boot method
    private function configureSns(): void
    {
        if (! $this->app->environment('staging', 'production')) {
            $this->app->bind(SnsService::class, function () {
                return new SnsHogService(config('services.sns', []));
            });
        }
    }
}
