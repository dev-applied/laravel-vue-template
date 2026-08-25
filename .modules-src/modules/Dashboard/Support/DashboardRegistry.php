<?php

declare(strict_types=1);

namespace Modules\Dashboard\Support;

use Closure;
use RuntimeException;

/**
 * Where a project declares what its dashboard shows. From AppServiceProvider:
 *
 *   app(DashboardRegistry::class)->stat(
 *       key: 'open_tickets', label: 'Open tickets', icon: 'support_agent',
 *       resolve: fn ($user) => ['value' => SupportTicket::whereStatus('open')->count()],
 *       cacheSeconds: 60,
 *   );
 *
 * The module ships no widgets of its own — it does not know what a client's
 * dashboard should say. It owns fetching, permission filtering, caching and
 * layout; the project owns the numbers.
 */
class DashboardRegistry
{
    /** @var array<string, DashboardWidget> */
    private array $widgets = [];

    public function stat(string $key, string $label, Closure $resolve, ?string $icon = null, ?string $color = null, ?string $ability = null, int $order = 100, ?int $cacheSeconds = null): void
    {
        $this->add(new DashboardWidget($key, $label, DashboardWidget::TYPE_STAT, $resolve, $ability, $icon, $color, $order, $cacheSeconds));
    }

    public function queue(string $key, string $label, Closure $resolve, ?string $icon = null, ?string $ability = null, int $order = 200, ?int $cacheSeconds = null): void
    {
        $this->add(new DashboardWidget($key, $label, DashboardWidget::TYPE_QUEUE, $resolve, $ability, $icon, null, $order, $cacheSeconds));
    }

    public function activity(string $key, string $label, Closure $resolve, ?string $icon = null, ?string $ability = null, int $order = 300, ?int $cacheSeconds = null): void
    {
        $this->add(new DashboardWidget($key, $label, DashboardWidget::TYPE_ACTIVITY, $resolve, $ability, $icon, null, $order, $cacheSeconds));
    }

    public function add(DashboardWidget $widget): void
    {
        $this->widgets[$widget->key] = $widget;
    }

    public function get(string $key): DashboardWidget
    {
        return $this->widgets[$key] ?? throw new RuntimeException("No dashboard widget registered for [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->widgets[$key]);
    }

    /** @return array<int, DashboardWidget> ordered */
    public function all(): array
    {
        $widgets = array_values($this->widgets);
        usort($widgets, fn (DashboardWidget $a, DashboardWidget $b) => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $widgets;
    }
}
