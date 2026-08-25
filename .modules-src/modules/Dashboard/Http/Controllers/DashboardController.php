<?php

declare(strict_types=1);

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Modules\Dashboard\Support\DashboardRegistry;
use Modules\Dashboard\Support\DashboardWidget;
use Throwable;

/**
 * One batched endpoint rather than one request per tile. A dashboard with eight
 * widgets should cost one round trip, not eight — that difference is most of
 * why hand-rolled dashboards feel slow.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardRegistry $registry): JsonResponse
    {
        $user    = $request->user();
        $only    = array_filter((array) $request->input('only', []));
        $widgets = [];

        foreach ($registry->all() as $widget) {
            if ($only !== [] && ! in_array($widget->key, $only, true)) {
                continue;
            }

            // A widget the viewer may not see is omitted entirely — not
            // returned empty, which would still leak that it exists.
            if ($widget->ability !== null && ! Gate::forUser($user)->allows($widget->ability)) {
                continue;
            }

            $widgets[] = [
                'key'   => $widget->key,
                'label' => $widget->label,
                'type'  => $widget->type,
                'icon'  => $widget->icon,
                'color' => $widget->color,
                ...$this->resolve($widget, $user),
            ];
        }

        return response()->json(['widgets' => $widgets]);
    }

    /**
     * One widget failing must not blank the whole dashboard — it reports its own
     * error and the rest still render.
     *
     * @return array{data: mixed, error: string|null}
     */
    private function resolve(DashboardWidget $widget, mixed $user): array
    {
        try {
            $resolver = fn (): mixed => ($widget->resolve)($user);

            $data = $widget->cacheSeconds
                // Cache per user: a scoped tile ("my open tasks") cached
                // globally would show one person another person's numbers.
                ? Cache::remember(
                    'dashboard:'.$widget->key.':'.($user?->getKey() ?? 'guest'),
                    $widget->cacheSeconds,
                    $resolver
                )
                : $resolver();

            return ['data' => $data, 'error' => null];
        } catch (Throwable $e) {
            report($e);

            return ['data' => null, 'error' => 'This panel could not be loaded.'];
        }
    }
}
