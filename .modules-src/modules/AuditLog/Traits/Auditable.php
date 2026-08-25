<?php

declare(strict_types=1);

namespace Modules\AuditLog\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Modules\AuditLog\Models\AuditLog;

/**
 * Records field-level changes for a model.
 *
 *   class Item extends Model
 *   {
 *       use Auditable;
 *
 *       // optional: never record these columns
 *       protected array $auditExclude = ['password', 'remember_token'];
 *
 *       // optional: record ONLY these columns
 *       protected array $auditOnly = ['status', 'owner_id'];
 *   }
 *
 * Complements the kernel's WhoDidIt: that stamps who last touched a row, this
 * keeps the history of what actually changed.
 */
trait Auditable
{
    /**
     * Columns never written to the trail, on top of anything the model hides.
     * Secrets must not be duplicated into a table that outlives the record.
     *
     * @var array<int, string>
     */
    protected static array $auditNeverCapture = [
        'password', 'remember_token', 'api_token', 'two_factor_secret',
        'two_factor_recovery_codes', 'access_token', 'refresh_token',
    ];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->recordAudit(AuditLog::EVENT_CREATED, [], $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $new = $model->auditableAttributes($model->getChanges());

            // getChanges() includes updated_at on every save; an entry whose
            // only "change" is the timestamp is noise, not history.
            unset($new[$model->getUpdatedAtColumn()]);

            if ($new === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $new);

            $model->recordAudit(AuditLog::EVENT_UPDATED, $model->auditableAttributes($old), $new);
        });

        static::deleted(function (Model $model) {
            $softDeleting = method_exists($model, 'trashed') && ! $model->isForceDeleting();

            $model->recordAudit(
                AuditLog::EVENT_DELETED,
                $model->auditableAttributes($model->getOriginal()),
                [],
            );

            unset($softDeleting);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $model->recordAudit(AuditLog::EVENT_RESTORED, [], $model->auditableAttributes($model->getAttributes()));
            });
        }
    }

    /** @return MorphMany<AuditLog, self> */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function recordAudit(string $event, array $old, array $new): void
    {
        $request = request();
        $capture = filter_var(env('AUDIT_CAPTURE_REQUEST', false), FILTER_VALIDATE_BOOL);

        AuditLog::create([
            'user_id'        => Auth::id(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id'   => $this->getKey(),
            'event'          => $event,
            'old_values'     => $old ?: null,
            'new_values'     => $new ?: null,
            'ip_address'     => $capture ? $request?->ip() : null,
            'user_agent'     => $capture ? mb_substr((string) $request?->userAgent(), 0, 1000) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        $only    = property_exists($this, 'auditOnly') ? $this->auditOnly : null;
        $exclude = property_exists($this, 'auditExclude') ? $this->auditExclude : [];

        if ($only !== null && $only !== []) {
            $attributes = array_intersect_key($attributes, array_flip($only));
        }

        // Hidden attributes are hidden for a reason; never let the trail leak
        // what the API refuses to serialise.
        $blocked = array_merge(static::$auditNeverCapture, $exclude, $this->getHidden());

        return array_diff_key($attributes, array_flip($blocked));
    }
}
