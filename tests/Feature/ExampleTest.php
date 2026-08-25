<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The SPA shell route responds.
     *
     * withoutVite() because the shell renders @vite(...) and would otherwise
     * demand a built public/build/manifest.json. A backend suite that requires
     * a frontend build passes locally — where the developer has just built —
     * and fails in CI, where the PHP job has no reason to run npm. The point of
     * this test is that the catch-all route resolves, not that assets compiled;
     * the frontend job covers the build.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $this->get('/')->assertStatus(200);
    }
}
