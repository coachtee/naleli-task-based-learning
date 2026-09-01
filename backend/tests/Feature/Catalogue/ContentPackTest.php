<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogue;

use App\Enums\EntitlementState;
use App\Models\Entitlement;
use App\Models\Learner;
use App\Models\Programme;
use App\Services\Content\ContentPacks;
use App\Support\CatalogueManifest;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which of the thirteen programmes can actually be taught.
 *
 * The school sells thirteen three-month blocks and the content for each is
 * written over months, so "sold" and "teachable" are permanently different
 * numbers. What is asserted here is that the difference is visible — because
 * the alternative, a sensible-looking default, means a Payroll learner opens
 * the app and is shown the Foundation course without anyone noticing.
 */
class ContentPackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
    }

    public function test_every_programme_names_the_pack_it_teaches_from(): void
    {
        $programmes = Programme::pluck('content_code', 'code');

        $this->assertCount(13, CatalogueManifest::CONTENT_PACKS);

        foreach (CatalogueManifest::CONTENT_PACKS as $code => $pack) {
            $this->assertSame($pack, $programmes[$code], "{$code} should teach from {$pack}");
        }

        // A programme with no pack named at all is the silent case — the one
        // where nothing reports a gap because nothing knows there is one.
        $this->assertEmpty(
            Programme::whereNull('content_code')->whereIn('code', array_keys(CatalogueManifest::CONTENT_PACKS))->get(),
        );
    }

    public function test_the_foundation_is_written_and_the_other_twelve_are_not_yet(): void
    {
        $packs = app(ContentPacks::class);

        $this->assertSame(['digital-foundation'], $packs->installed());
        $this->assertTrue($packs->isInstalled('digital-foundation'));
        $this->assertFalse($packs->isInstalled('people-payroll-operations'));
        $this->assertFalse($packs->isInstalled(null));

        $status = collect($packs->status())->keyBy('code');
        $this->assertTrue($status['DOPF']['installed']);
        $this->assertGreaterThan(0, $status['DOPF']['tasks']);
        $this->assertFalse($status['PPO']['installed']);
    }

    public function test_the_catalogue_endpoint_says_what_can_be_taught_today(): void
    {
        $response = $this->getJson('/api/v1/content')->assertOk();

        $rows = collect($response->json('data'))->keyBy('programme_code');

        $this->assertCount(13, $rows);
        $this->assertTrue($rows['DOPF']['installed']);
        $this->assertSame('digital-foundation', $rows['DOPF']['content_code']);
        $this->assertFalse($rows['PPO']['installed']);
        // Named, not hidden. A gap you can list is a gap somebody can close.
        $this->assertSame('people-payroll-operations', $rows['PPO']['content_code']);
    }

    public function test_a_pack_nobody_has_written_is_a_404_not_a_substitute(): void
    {
        $this->getJson('/api/v1/content/digital-foundation')->assertOk();
        $this->getJson('/api/v1/content/people-payroll-operations')->assertNotFound();
    }

    public function test_an_entitlement_tells_the_client_whether_its_course_exists(): void
    {
        $learner = Learner::create([
            'learner_ref' => 'NAL-2026-00001',
            'first_registered_year' => 2026,
            'first_name' => 'Thabiso',
            'last_name' => 'Mokoena',
            'email' => 'thabiso@example.co.za',
        ]);

        foreach (['DOPF', 'PPO'] as $code) {
            Entitlement::create([
                'learner_id' => $learner->id,
                'programme_id' => Programme::where('code', $code)->value('id'),
                'state' => EntitlementState::ACTIVE,
                'unlocked_at' => now(),
            ]);
        }

        $token = $learner->createToken('Test device', ['learner'])->plainTextToken;

        $states = collect(
            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson('/api/v1/me/entitlements')->assertOk()->json('data'),
        )->keyBy('programme_code');

        $this->assertTrue($states['DOPF']['content_installed']);
        $this->assertFalse($states['PPO']['content_installed'], 'sold, but nobody has written it yet');
    }

    public function test_content_check_passes_and_can_be_made_to_fail_on_gaps(): void
    {
        $this->artisan('content:check')->assertSuccessful();

        // The deployment check: every programme on sale must be teachable.
        // It fails today, on purpose, and that is the number to watch.
        $this->artisan('content:check --strict')->assertFailed();
    }
}
