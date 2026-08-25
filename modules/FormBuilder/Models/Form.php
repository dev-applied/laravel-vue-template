<?php

declare(strict_types=1);

namespace Modules\FormBuilder\Models;

use App\Traits\WhoDidIt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\FormBuilder\Database\Factories\FormFactory;

class Form extends Model
{
    use HasFactory, WhoDidIt;

    protected $fillable = [
        'name', 'slug', 'description', 'schema',
        'success_message', 'is_published', 'is_public', 'closes_at',
    ];

    protected $casts = [
        'schema'       => 'array',
        'is_published' => 'boolean',
        'is_public'    => 'boolean',
        'closes_at'    => 'datetime',
    ];

    protected $attributes = [
        'is_published' => false,
        'is_public'    => false,
    ];

    /** @return HasMany<FormSubmission, self> */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Accepting submissions right now.
     */
    public function isOpen(): bool
    {
        return $this->is_published
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        return (array) ($this->schema ?? []);
    }

    protected static function newFactory(): Factory
    {
        return FormFactory::new();
    }
}
