<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * This replaces Laravel's stock test, which asserted that GET / returns the
 * welcome page. That route is gone: in production the front controller is
 * served from public_html/admin, so the Filament panel mounts at the directory
 * root, and the welcome stub was claiming the URI the dashboard needed —
 * kcs.edu.za/admin answered 405 while every resource beneath it worked.
 *
 * What is worth asserting instead is that the application answers at all, and
 * that the one unauthenticated endpoint the Android app relies on to check
 * connectivity keeps its contract.
 */
class ExampleTest extends TestCase
{
    public function test_the_health_endpoint_answers_without_authentication(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'kcs-education-backend')
            ->assertJsonPath('api_version', 'v1');
    }

    public function test_there_is_no_public_web_page_at_the_root(): void
    {
        // Nothing should occupy the root URI. In production that is where the
        // dashboard lives.
        $this->get('/')->assertNotFound();
    }
}
