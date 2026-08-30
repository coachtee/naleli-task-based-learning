<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogue;

use App\Enums\ActivationRule;
use App\Enums\BillingModel;
use App\Enums\OfferingStatus;
use App\Enums\ProgrammeStatus;
use App\Enums\ProgrammeTier;
use App\Enums\RequirementRule;
use App\Models\Offering;
use App\Models\Programme;
use App\Support\CatalogueManifest;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * kcs.edu.za is the source of truth for what KCS sells, and this is what stops
 * the backend drifting away from it.
 *
 * The audit that produced CatalogueManifest found a backend catalogue taken
 * from the site's navigation while every real application named something from
 * the application form — two lists nothing had ever compared. These assertions
 * make that comparison automatic: a programme cannot enter the database
 * without appearing in the manifest, and it cannot enter the manifest without
 * a URL saying where on the site it was found.
 *
 * If KCS changes its catalogue, this test is meant to fail. Re-audit the live
 * site, update the manifest, and the failure goes away — which is the point.
 */
class CatalogueDriftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
    }

    public function test_the_database_holds_exactly_what_the_manifest_declares(): void
    {
        $expected = CatalogueManifest::codes();
        sort($expected);

        $actual = Programme::where('status', '!=', ProgrammeStatus::ARCHIVED)
            ->pluck('code')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            $expected,
            $actual,
            'The seeded catalogue no longer matches the audited website catalogue.',
        );
    }

    public function test_every_programme_says_where_on_the_website_it_came_from(): void
    {
        foreach (Programme::all() as $programme) {
            $this->assertNotNull(
                $programme->source_url,
                "Programme [{$programme->code}] has no source_url. Every programme must be traceable to a published page or form on kcs.edu.za.",
            );

            $this->assertStringStartsWith(
                'https://www.kcs.edu.za/',
                $programme->source_url,
                "Programme [{$programme->code}] cites a source outside kcs.edu.za.",
            );
        }
    }

    /**
     * The specific names that prompted this audit. They are not on the live
     * site and must never be seeded back in.
     */
    public function test_programmes_the_website_does_not_publish_are_absent(): void
    {
        $notOnTheSite = [
            'AI Workplace Administration',
            'Cybersecurity Foundations',
            'Software Development',
            'Digital Skills Lab',
        ];

        foreach ($notOnTheSite as $name) {
            $this->assertSame(
                0,
                Programme::where('name', $name)->count(),
                "[{$name}] is not published on kcs.edu.za and must not be in the catalogue.",
            );
        }
    }

    public function test_every_block_runs_the_advertised_february_2027_intake(): void
    {
        // "One block = 3 months · Next intake February 2027", on every card.
        foreach (Programme::whereIn('code', CatalogueManifest::codes())->get() as $programme) {
            $this->assertSame(
                ['February 2027'],
                $programme->intakes()->pluck('label')->all(),
                "[{$programme->code}] does not run the advertised intake.",
            );
        }
    }

    public function test_the_catalogue_is_one_foundation_seven_career_and_five_professional(): void
    {
        $byTier = Programme::whereIn('code', CatalogueManifest::codes())
            ->get()
            ->groupBy(fn (Programme $p): string => $p->tier->value)
            ->map->count();

        $this->assertSame(1, $byTier['foundation'] ?? 0);
        $this->assertSame(7, $byTier['career_module'] ?? 0);
        $this->assertSame(5, $byTier['professional'] ?? 0);
        $this->assertCount(13, CatalogueManifest::codes());
    }

    public function test_every_block_costs_the_same_r500_plus_r950_times_three(): void
    {
        $offerings = Offering::whereRelation('programme', 'status', ProgrammeStatus::OPEN)->get();

        $this->assertCount(13, $offerings);

        foreach ($offerings as $offering) {
            $this->assertSame(335000, $offering->price_cents, "[{$offering->code}] is not R3,350.");
            $this->assertSame(50000, $offering->deposit_cents);
            $this->assertSame(3, $offering->instalment_count);
            $this->assertSame(OfferingStatus::OPEN, $offering->status);
        }
    }

    public function test_every_specialisation_requires_the_foundation_first(): void
    {
        $foundation = Programme::where('code', 'DOPF')->firstOrFail();

        $this->assertSame(
            RequirementRule::NONE,
            $foundation->requirements->first()->rule_type,
            'Nothing precedes the Foundation.',
        );

        foreach (Programme::whereIn('code', CatalogueManifest::codes())->where('code', '!=', 'DOPF')->get() as $p) {
            $this->assertSame(RequirementRule::COMPLETED_PROGRAMME, $p->requirements->first()->rule_type);
            $this->assertSame($foundation->id, $p->requirements->first()->requires_programme_id);
        }
    }

    public function test_the_old_catalogue_is_archived_rather_than_deleted(): void
    {
        // 35 real applications and 15 Excel bookings already name these.
        $retired = Programme::create([
            'code' => 'DOA',
            'name' => 'Digital Office Administrator',
            'slug' => 'digital-office-administrator',
            'source_url' => 'https://www.kcs.edu.za/application/',
            'tier' => ProgrammeTier::SHORT_COURSE,
            'status' => ProgrammeStatus::OPEN,
        ]);

        $offering = Offering::create([
            'code' => 'DOA-2026',
            'programme_id' => $retired->id,
            'name' => 'Digital Office Administrator',
            'billing_model' => BillingModel::ONE_TIME,
            'price_cents' => 250000,
            'access_duration_days' => 90,
            'activation_rule' => ActivationRule::ON_FIRST_PAYMENT,
            'status' => OfferingStatus::OPEN,
        ]);

        $this->seed(ProgrammeSeeder::class);

        $this->assertSame(ProgrammeStatus::ARCHIVED, $retired->fresh()->status);
        $this->assertSame(OfferingStatus::ARCHIVED, $offering->fresh()->status);
        $this->assertNotNull($retired->fresh(), 'A retired programme must never be deleted.');
    }

    /**
     * The webhook maps the form's free-text option strings onto programme
     * codes. A typo there files a real application against the wrong
     * programme silently, so every code it can produce must exist.
     */
    public function test_every_code_the_webhook_can_produce_is_a_real_programme(): void
    {
        $map = config('webhooks.fluentform.programme_map');

        $this->assertNotEmpty($map, 'The programme map is empty; every application would arrive unmapped.');

        foreach ($map as $option => $code) {
            $this->assertContains(
                $code,
                CatalogueManifest::codes(),
                "The form option [{$option}] maps to [{$code}], which is not in the catalogue.",
            );
        }
    }
}
