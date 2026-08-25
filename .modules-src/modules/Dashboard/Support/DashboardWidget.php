<?php

declare(strict_types=1);

namespace Modules\Dashboard\Support;

use Closure;

/**
 * One dashboard panel.
 *
 * `resolve` runs per request and receives the viewing user, so a tile can be
 * scoped ("my open tasks") rather than global. It is only ever called for
 * widgets the viewer is allowed to see.
 */
class DashboardWidget
{
    public const TYPE_STAT = 'stat';

    public const TYPE_QUEUE = 'queue';

    public const TYPE_ACTIVITY = 'activity';

    /** @param Closure(mixed): mixed $resolve */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly Closure $resolve,
        public readonly ?string $ability = null,
        public readonly ?string $icon = null,
        public readonly ?string $color = null,
        public readonly int $order = 100,
        public readonly ?int $cacheSeconds = null,
    ) {}
}
