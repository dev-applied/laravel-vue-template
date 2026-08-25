<?php

declare(strict_types=1);

namespace Modules\Favorites\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Favorites\Support\FavoritableRegistry;

/**
 * @mixin \Modules\Favorites\Models\Favorite
 */
class FavoriteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $target = $this->whenLoaded('favoritable');

        return [
            'id' => $this->id,
            // The registry alias, never the class name. Leaking
            // App\Models\Article tells a caller the app's namespace layout, and
            // it is the alias they have to send back anyway.
            'type'        => app(FavoritableRegistry::class)->aliasForType($this->favoritable_type),
            'favoritedAt' => $this->created_at,

            // A favourite outlives a hard-deleted target, so this is nullable
            // by design rather than by accident. The list renders those as
            // "no longer available" instead of failing on the whole page.
            'record' => $this->when(
                $this->relationLoaded('favoritable'),
                fn () => $target === null ? null : [
                    'id'    => $target->getKey(),
                    'label' => $this->labelFor($target),
                ]
            ),
        ];
    }

    /**
     * A human label without knowing the model.
     *
     * Tries the conventional columns in order. A project wanting something
     * else gives its model a `favoriteLabel()`.
     */
    protected function labelFor(object $target): string
    {
        if (method_exists($target, 'favoriteLabel')) {
            return (string) $target->favoriteLabel();
        }

        foreach (['title', 'name', 'label', 'subject'] as $column) {
            $value = $target->{$column} ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($target).' #'.$target->getKey();
    }
}
