<?php

declare(strict_types=1);

namespace App\Interfaces;

use Illuminate\Database\Eloquent\Builder;

interface WithSelected
{
    public function scopeWithSelected(Builder $query, $selected, $key): Builder;
}
