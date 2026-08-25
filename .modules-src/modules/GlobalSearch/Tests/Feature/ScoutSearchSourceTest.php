<?php

declare(strict_types=1);

use Modules\GlobalSearch\Support\ScoutSearchSource;

test('it builds a registry query closure from a Scout model', function () {
    // Scout is an install-time option, so the helper has to be usable without a
    // configured engine in the suite. What is asserted here is the CONTRACT the
    // registry depends on — a closure taking a term and returning an Eloquent
    // builder — not that a search engine returned anything.
    if (! class_exists(\Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('laravel/scout is not installed — this is the scout=off variant.');
    }

    $model = new class extends \Illuminate\Database\Eloquent\Model
    {
        use \Laravel\Scout\Searchable;

        protected $table = 'users';
    };

    config()->set('scout.driver', 'database');

    $closure = ScoutSearchSource::for($model::class, with: []);

    expect($closure)->toBeCallable()
        ->and($closure('anything'))->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class);
});
