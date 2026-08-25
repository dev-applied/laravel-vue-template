<?php

declare(strict_types=1);

namespace Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Models\Notification;

/**
 * Flattens the notification's `data` payload into the shape the bell and the
 * notifications page consume, so neither has to know about Laravel's storage
 * format. Keys are camelCase to match the frontend's NotificationItem type.
 *
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = (array) $this->data;

        return [
            'id'        => $this->id,
            'type'      => $this->type,
            'title'     => $data['title'] ?? 'Notification',
            'body'      => $data['body'] ?? null,
            'icon'      => $data['icon'] ?? null,
            'color'     => $data['color'] ?? null,
            'url'       => $data['url'] ?? null,
            'readAt'    => $this->read_at,
            'createdAt' => $this->created_at,
        ];
    }
}
