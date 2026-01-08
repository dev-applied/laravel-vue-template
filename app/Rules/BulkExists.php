<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class BulkExists implements ValidationRule
{
    /**
     * @param  string  $keyPath  The dot-notation path to the ID (e.g., '*.product_id')
     * @param  string  $table  The database table to check against
     * @param  string  $column  The database column (default 'id')
     * @param  string|null  $message  Custom error message (optional)
     */
    public function __construct(
        protected string $keyPath,
        protected string $table,
        protected string $column = 'id',
        protected ?string $message = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || empty($value)) {
            return;
        }

        // 1. Extract all IDs using wildcard support (handles nested arrays)
        // data_get($products, '*.sizes.*.product_size_id') works perfectly
        $ids = collect(data_get($value, $this->keyPath))
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return;
        }

        // 2. Perform a single DB query (No N+1)
        $count = DB::table($this->table)
            ->whereIn($this->column, $ids)
            ->count();

        // 3. Compare counts
        if ($count !== $ids->count()) {
            $fail($this->message ?? "One or more selected entries for {$this->table} do not exist.");
        }
    }
}
