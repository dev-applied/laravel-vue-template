<?php

declare(strict_types=1);

namespace Modules\Example\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Example\Database\Factories\ExampleNoteFactory;

class ExampleNote extends Model
{
    /** @use HasFactory<ExampleNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'note',
    ];

    // Module factories live inside the module, outside Laravel's default
    // Database\Factories\ discovery — every module model points at its
    // factory explicitly.
    protected static function newFactory(): ExampleNoteFactory
    {
        return ExampleNoteFactory::new();
    }
}
