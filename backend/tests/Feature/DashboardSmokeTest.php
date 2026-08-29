<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Widgets\PipelineOverview;
use App\Filament\Widgets\WorkQueue;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_the_overview_dashboard_loads(): void
    {
        $this->seed(ProgrammeSeeder::class);

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->get('/admin')
            ->assertSuccessful();
    }

    /**
     * Widgets render lazily over Livewire, so hitting the dashboard proves
     * only that the shell loaded. These mount the widgets themselves, which is
     * where the real risk sits: both run live queries against enums and
     * relations that a rename would silently break.
     */
    public function test_the_overview_widgets_run_their_queries(): void
    {
        $this->seed(ProgrammeSeeder::class);

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));

        Livewire::test(PipelineOverview::class)
            ->assertSuccessful()
            ->assertSee('New registrations')
            ->assertSee('Profile incomplete');

        Livewire::test(WorkQueue::class)
            ->assertSuccessful()
            ->assertSee('Nothing waiting');
    }

    /**
     * Edit pages are where a renamed column or a stale enum default hides —
     * the list can be perfectly happy while the form throws.
     */
    public function test_the_registration_edit_page_loads_with_its_form(): void
    {
        $this->seed(ProgrammeSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $application = Application::firstOrFail();

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->get("/admin/applications/{$application->id}/edit")
            ->assertSuccessful()
            ->assertSee('How it is being paid for');
    }

    public function test_every_resource_list_page_loads_for_an_administrator(): void
    {
        $this->seed(ProgrammeSeeder::class);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $pages = [
            '/admin/learners',
            '/admin/programmes',
            '/admin/offerings',
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
