<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemStatus: string
{
    case Draft    = 'draft';
    case Active   = 'active';
    case Archived = 'archived';

    /** Convenience for v-select :items binding. */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['title' => $case->label(), 'value' => $case->value],
            self::cases(),
        );
    }

    /** Human label, e.g. for select dropdowns. */
    public function label(): string
    {
        return match ($this) {
            self::Draft    => 'Draft',
            self::Active   => 'Active',
            self::Archived => 'Archived',
        };
    }
}
