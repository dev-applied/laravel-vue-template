<?php

declare(strict_types=1);

namespace Modules\Realtime\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * What the browser needs to open a socket.
 *
 * Served rather than baked into the bundle so the same build runs against
 * staging and production — a Capacitor app in particular is compiled once and
 * pointed at an API afterwards, and a VITE_ variable frozen at build time is
 * the wrong host from then on.
 *
 * Everything here is public by design: the app KEY is not a secret (the secret
 * is what signs the auth response, and that never leaves the server).
 */
class RealtimeConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'server'            => config('realtime.server'),
                'key'               => config('realtime.client.key'),
                'host'              => config('realtime.client.host'),
                'port'              => config('realtime.client.port'),
                'scheme'            => config('realtime.client.scheme'),
                'cluster'           => config('realtime.client.cluster'),
                'staleAfterSeconds' => config('realtime.stale_after_seconds'),
                // A client that is told the server is not configured can say so
                // instead of retrying a socket that will never open.
                'enabled' => filled(config('realtime.client.key')),
            ],
        ]);
    }
}
