<?php

declare(strict_types=1);

namespace App\Mixins;

use Closure;

/**
 * Class HasManyMixin
 *
 * @mixin \Illuminate\Database\Eloquent\Relations\HasMany
 */
class HasManyMixin
{
    public function syncWithoutDetaching(): Closure
    {
        return function ($data): array {
            return $this->sync()($data, false);
        };
    }

    public function sync(): Closure
    {
        return function ($data, $deleting = true): array {
            $changes = [];

            $relatedKeyName = $this->getRelated()->getKeyName();

            // First we need to attach any of the associated models that are not currently
            // in the child entity table. We'll spin through the given IDs, checking to see
            // if they exist in the array of current ones, and if not we will insert.
            $current = $this->newQuery()->pluck(
                $relatedKeyName
            )->all();

            // Separate the submitted data into "update" and "new"
            $updateRows = [];
            $newRows    = [];

            foreach ($data as $row) {
                // We determine "updateable" rows as those whose $relatedKeyName (usually 'id') is set, not empty, and
                // match a related row in the database.
                if (isset($row[$relatedKeyName]) && ! empty($row[$relatedKeyName]) && in_array($row[$relatedKeyName], $current)) {
                    $id              = $row[$relatedKeyName];
                    $updateRows[$id] = $row;
                } else {
                    $newRows[] = $row;
                }
            }

            // Next, we'll determine the rows in the database that aren't in the "update" list.
            // These rows will be scheduled for deletion.  Again, we determine based on the relatedKeyName (typically 'id').
            $updateIds = array_keys($updateRows);
            $deleteIds = [];

            foreach ($current as $currentId) {
                if (! in_array($currentId, $updateIds)) {
                    $deleteIds[] = $currentId;
                }
            }

            // Delete any non-matching rows
            if ($deleting && count($deleteIds) > 0) {
                $this->getRelated()->destroy($deleteIds);
            }

            $changes['deleted'] = $this->castKeys($deleteIds);

            // Update the updatable rows
            foreach ($updateRows as $id => $row) {
                $this->getRelated()->where($relatedKeyName, $id)
                    ->update($row);
            }

            $changes['updated'] = $this->castKeys($updateIds);

            // Insert the new rows
            $newIds = [];

            foreach ($newRows as $row) {
                $newModel = $this->create($row);
                $newIds[] = $newModel->$relatedKeyName;
            }

            $changes['created'] = $this->castKeys($newIds);

            return $changes;
        };
    }

    /**
     * Cast the given keys to integers if they are numeric and string otherwise.
     */
    protected function castKeys(): Closure
    {
        return function (array $keys): array {
            return (array) array_map(function ($v) {
                return $this->castKey($v);
            }, $keys);
        };
    }

    /**
     * Cast the given key to an integer if it is numeric.
     */
    protected function castKey(): Closure
    {
        return function (int|string $key): int|string {
            return is_numeric($key) ? (int) $key : (string) $key;
        };
    }
}
