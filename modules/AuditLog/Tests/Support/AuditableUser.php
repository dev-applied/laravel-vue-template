<?php

declare(strict_types=1);

namespace Modules\AuditLog\Tests\Support;

use App\Models\User;
use Modules\AuditLog\Traits\Auditable;

/**
 * Test subject. The module cannot assume any project model is auditable, and
 * the kernel contract only guarantees User — so the suite audits a subclass of
 * it, backed by the same table.
 */
class AuditableUser extends User
{
    use Auditable;

    protected $table = 'users';

    /** Prove auditExclude keeps a named column out of the trail. */
    protected array $auditExclude = ['email_verified_at'];
}
