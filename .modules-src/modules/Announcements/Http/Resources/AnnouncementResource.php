<?php

declare(strict_types=1);

namespace Modules\Announcements\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Announcements\Models\Announcement;

/**
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'title'                   => $this->title,
            'body'                    => $this->body,
            'level'                   => $this->level,
            'placement'               => $this->placement,
            'audience'                => $this->audience,
            'dismissible'             => $this->dismissible,
            'requiresAcknowledgement' => $this->requires_acknowledgement,
            'actionLabel'             => $this->action_label,
            'actionUrl'               => $this->action_url,
            'startsAt'                => $this->starts_at?->toIso8601String(),
            'endsAt'                  => $this->ends_at?->toIso8601String(),
            'publishedAt'             => $this->published_at?->toIso8601String(),
            'isLive'                  => $this->isLive(),
            'createdAt'               => $this->created_at?->toIso8601String(),
            'dismissalCount'          => $this->whenCounted('dismissals'),
        ];
    }
}
