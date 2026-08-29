<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard is the whole of Phase 1's user interface, so "does it load"
 * is worth asserting rather than assuming. Each resource is hit individually:
 * a broken column reference on one page would otherwise only surface when a
 * registrar clicked it.
 */
class DashboardSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_panel_requires_a_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_every_resource_list_page_loads_for_an_administrator(): void
    {
        $this->seed(ProgrammeSeeder::class);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $pages = [
            '/admin/learners',
            '/admin/programmes',
            '/admin/applications',
            '/admin/enrolments',
            '/admin/invoices',
            '/admin/payments',
            '/admin/access-tokens',
            '/admin/entitlements',
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)
                ->get($page)
                ->assertSuccessful();
        }
    }
}
