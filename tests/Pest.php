<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

// Module Feature tests (modules/<Name>/Tests/Feature) get the same base —
// real DB via RefreshDatabase, never mocked. See docs/modules.md.
uses(TestCase::class, RefreshDatabase::class)->in('../modules/*/Tests/Feature');
