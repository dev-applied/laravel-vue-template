<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueCatWebhookEvent extends Model
{
    protected $table = 'revenuecat_webhook_events';

    protected $fillable = ['event_id', 'event_type', 'app_user_id', 'environment', 'event_at_ms', 'payload'];

    protected $casts = ['payload' => 'array'];
}
