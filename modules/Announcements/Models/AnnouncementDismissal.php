<?php

declare(strict_types=1);

namespace Modules\Announcements\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementDismissal extends Model
{
    protected $fillable = ['announcement_id', 'user_id', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime'];

    /** @return BelongsTo<Announcement, self> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
