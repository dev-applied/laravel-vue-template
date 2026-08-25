<?php

declare(strict_types=1);

return [
    // 'reverb' (default, self-hosted) or 'pusher'. Both speak the Pusher
    // protocol, so the frontend is identical — only the host changes.
    'server' => env('REALTIME_SERVER', 'reverb'),

    // What the BROWSER connects to. Not the same as what PHP connects to when
    // it broadcasts: in Docker the browser reaches the host through Traefik
    // while PHP reaches the container directly, and conflating the two is the
    // single most common way this is misconfigured.
    'client' => [
        'key' => env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY', '')),
        'host' => env('VITE_REVERB_HOST', env('APP_DOMAIN', 'localhost')),
        'port' => (int) env('VITE_REVERB_PORT', 443),
        'scheme' => env('VITE_REVERB_SCHEME', 'https'),
        'cluster' => env('VITE_PUSHER_APP_CLUSTER', 'mt1'),
    ],

    // How long a client may go without a pong before the composable reports the
    // connection lost. Reverb's own default is 30s; this is what the UI uses to
    // decide when to say something, and it is deliberately longer so a single
    // slow beat does not flash a banner at everyone.
    'stale_after_seconds' => (int) env('REALTIME_STALE_AFTER', 45),
];
