<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Delivery log.
 *
 * Kept because "did they get the code?" is the single most common support
 * question on any app that texts people, and without a log the honest answer is
 * a shrug and a vendor dashboard login.
 *
 * @property string $phone_number
 * @property string $body
 * @property string $status
 * @property string|null $driver
 * @property string|null $vendor_id
 * @property string|null $error
 */
class SmsMessage extends Model
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SUPPRESSED = 'suppressed';

    protected $table = 'sms_messages';

    protected $fillable = [
        'phone_number', 'body', 'status', 'driver', 'vendor_id', 'error', 'notifiable_type', 'notifiable_id',
    ];
}
