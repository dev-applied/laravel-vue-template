<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceAvailability extends Model
{
    protected $table = 'resource_availability';

    protected $fillable = ['bookable_resource_id', 'day_of_week', 'opens_at', 'closes_at'];

    protected $casts = ['day_of_week' => 'integer'];
}
