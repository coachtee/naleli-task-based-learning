<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogue;

use App\Enums\OfferingStatus;
use App\Enums\ProgrammeStatus;
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
     * The safeguard that actually protects a learner: an offering can only be
     * open if the website publishes a price for it. Everything else stays
     * draft, and ApplicationAcceptor refuses to sell a draft.
     */
    public function test_nothing_is_sellable_without_a_price_published_on_the_website(): void
    {
        foreach (Offering::where('status', OfferingStatus::OPEN)->get() as $offering) {
            $this->assertGreaterThan(
                0,
                $offering->price_cents,
                "Offering [{$offering->code}] is open at R0. An unpriced offering must stay draft.",
            );

            $this->assertContains(
                $offering->programme->code,
                CatalogueManifest::sellableCodes(),
                "Offering [{$offering->code}] is open but its programme has no price published on kcs.edu.za.",
            );
        }
    }

    public function test_the_career_module_price_is_the_one_the_home_page_advertises(): void
    {
        // "NIBS Career Modules — R500 once-off registration, R950 a month."
        $offering = Offering::whereRelation('programme', 'code', 'PPO')->firstOrFail();

        $this->assertSame(335000, $offering->price_cents);
        $this->assertSame(50000, $offering->deposit_cents);
        $this->assertSame(3, $offering->instalment_count);
        $this->assertSame(OfferingStatus::OPEN, $offering->status);
    }

    public function test_basic_excel_is_the_r500_short_course_the_site_actually_books(): void
    {
        $offering = Offering::whereRelation('programme', 'code', 'EXCEL')->firstOrFail();

        $this->assertSame(50000, $offering->price_cents);
        $this->assertSame(OfferingStatus::OPEN, $offering->status);
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

    public function test_the_cohorts_are_the_ones_the_application_form_offers(): void
    {
        // A submission naming "DOA-F2F-001" has to resolve without translation,
        // because that is the string the live form actually posts.
        $doa = Programme::where('code', 'DOA')->firstOrFail();

        $this->assertSame(
            ['DOA-F2F-001', 'DOA-F2F-002', 'DOA-F2F-003'],
            $doa->intakes()->orderBy('starts_on')->pluck('label')->all(),
        );

        $this->assertSame(
            0,
            Programme::first()->intakes()->where('label', 'February 2027')->count(),
            'February 2027 was never published on kcs.edu.za and must not come back.',
        );
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

    /**
     * The options 35 real applicants actually chose. If the form is reworded
     * and this map is not updated, those submissions stop resolving — this is
     * the assertion that catches it.
     */
    public function test_the_options_real_applicants_chose_all_resolve(): void
    {
        $chosen = [
            'Digital Office Administrator',
            'IT Support Specialist',
            'Data Capturing Specialist',
            'Junior Software Developer',
            'Junior Cybersecurity Analyst',
            'Cybersecurity Analyst',
            'Office Administration',
            'OA-001 | Office Administrator | NQF 5 | 16 Apr 2026 - 15 Apr 2027',
        ];

        $map = config('webhooks.fluentform.programme_map');

        foreach ($chosen as $option) {
            $this->assertArrayHasKey(
                $option,
                $map,
                "A real applicant chose [{$option}] and the webhook cannot map it.",
            );
        }
    }

    public function test_a_programme_dropped_from_the_website_is_archived_rather_than_deleted(): void
    {
        // A learner may already be enrolled on it, so the row has to survive.
        $ghost = Programme::create([
            'code' => 'GHOST',
            'name' => 'Programme No Longer Offered',
            'slug' => 'programme-no-longer-offered',
            'source_url' => 'https://www.kcs.edu.za/career-pathways/gone/',
            'tier' => Programme::first()->tier,
            'status' => ProgrammeStatus::OPEN,
        ]);

        $this->seed(ProgrammeSeeder::class);

        $this->assertSame(ProgrammeStatus::ARCHIVED, $ghost->fresh()->status);
        $this->assertNotNull($ghost->fresh(), 'A retired programme must never be deleted.');
    }
}
