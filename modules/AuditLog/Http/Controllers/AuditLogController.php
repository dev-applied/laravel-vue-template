<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AuditLog\Http\Resources\AuditLogResource;
use Modules\AuditLog\Models\AuditLog;

/**
 * Read-only by design: an audit trail nobody can edit is the only kind worth
 * keeping. There is no store/update/destroy — entries are written by the
 * Auditable trait and removed only by the retention prune command.
 *
 * Visibility is gated by the `viewAuditLog` gate, which the PROJECT defines.
 * The module refuses to guess who may read it: the trail can contain any field
 * of any audited model.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAuditLog');

        $logs = AuditLog::query()
            ->with('user')
            ->filter($request->only(['auditable_type', 'auditable_id', 'user_id', 'event', 'from', 'to']))
            ->latest('id')
            ->vuetifyPaginate();

        $logs->setCollection(
            $logs->getCollection()->map(fn (AuditLog $log) => new AuditLogResource($log))->collect()
        );

        return response()->json($logs);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $this->authorize('viewAuditLog');
        $auditLog->load('user');

        return response()->json(['log' => new AuditLogResource($auditLog)]);
    }
}
