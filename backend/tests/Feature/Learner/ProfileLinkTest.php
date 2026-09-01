<?php

declare(strict_types=1);

namespace Tests\Feature\Learner;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Learner;
use App\Models\Programme;
use App\Services\Registration\LearnerLinks;
use App\Services\Registration\ProfileCompleteness;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The learner finishing their own registration from a link we sent them.
 *
 * The link is the credential, so what is asserted here is mostly what the
 * link REFUSES: no signature, a tampered learner id, an expired window. A
 * page that hands out somebody's address because the URL was guessed is worse
 * than no page at all.
 */
class ProfileLinkTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_SA_ID = '9001015800088';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
    }

    public function test_a_learner_opens_their_own_link_and_sees_what_is_missing(): void
    {
        $learner = $this->learner();

        $this->get(app(LearnerLinks::class)->profile($learner))
            ->assertOk()
            ->assertSee('Finish your registration')
            ->assertSee($learner->learner_ref)
            ->assertSee('9 still to go')
            ->assertSee('Your identification');

        $this->assertContains('Home address', app(ProfileCompleteness::class)->missing($learner));
    }

    public function test_an_unsigned_link_is_refused(): void
    {
        $learner = $this->learner();

        // The URL is guessable; the signature is not.
        $this->get("/my/profile/{$learner->id}")->assertForbidden();
    }

    public function test_a_link_edited_to_point_at_another_learner_is_refused(): void
    {
        $mine = $this->learner();
        $someoneElse = $this->learner('Other', 'Person', 'other@example.co.za');

        $tampered = str_replace(
            "/my/profile/{$mine->id}?",
            "/my/profile/{$someoneElse->id}?",
            app(LearnerLinks::class)->profile($mine),
        );

        $this->get($tampered)->assertForbidden();
    }

    public function test_an_expired_link_is_refused(): void
    {
        $learner = $this->learner();
        $link = app(LearnerLinks::class)->profile($learner, days: 30);

        $this->travel(31)->days();

        $this->get($link)->assertForbidden();
    }

    public function test_saving_details_moves_the_learner_off_profile_incomplete(): void
    {
        $learner = $this->learner();
        $application = Application::where('learner_id', $learner->id)->sole();
        $application->update(['status' => ApplicationStatus::PROFILE_INCOMPLETE]);

        $this->post(app(LearnerLinks::class)->profile($learner), [
            'id_type' => 'sa_id',
            'id_number' => self::VALID_SA_ID,
            'phone' => '082 123 4567',
            'email' => 'thabiso@example.co.za',
            'address_line' => '824 Sontonga Road',
            'city' => 'Katlehong',
            'province' => 'Gauteng',
            'highest_qualification' => 'Matric (Grade 12)',
            'school_or_institution' => 'Katlehong High',
            'employment_status' => 'Unemployed',
        ])->assertRedirect();

        $learner->refresh();

        $this->assertTrue(app(ProfileCompleteness::class)->isComplete($learner));
        $this->assertSame(ApplicationStatus::REGISTERED, $application->fresh()->status);

        // The ID was written through the registry: encrypted, hashed, masked,
        // and self-verified because a valid SA ID proves its own date of birth.
        $this->assertNotNull($learner->id_number_hash);
        $this->assertNotNull($learner->id_number_masked);
        $this->assertTrue($learner->hasVerifiedIdentity());
        $this->assertSame('1990-01-01', $learner->date_of_birth->format('Y-m-d'));
    }

    public function test_a_half_filled_form_never_wipes_what_is_already_there(): void
    {
        $learner = $this->learner();
        $learner->update(['city' => 'Katlehong', 'province' => 'Gauteng']);

        // A learner saves on a taxi, loses signal, comes back and sends only
        // the fields they had reached.
        $this->post(app(LearnerLinks::class)->profile($learner), [
            'address_line' => '824 Sontonga Road',
            'city' => '',
            'province' => '',
        ])->assertRedirect();

        $learner->refresh();
        $this->assertSame('824 Sontonga Road', $learner->address_line);
        $this->assertSame('Katlehong', $learner->city, 'a blank field means unanswered, not delete');
        $this->assertSame('Gauteng', $learner->province);
    }

    public function test_an_id_belonging_to_someone_else_is_refused(): void
    {
        $first = $this->learner();
        $second = $this->learner('Naledi', 'Dlamini', 'naledi@example.co.za');

        $this->post(app(LearnerLinks::class)->profile($first), [
            'id_type' => 'sa_id', 'id_number' => self::VALID_SA_ID,
        ])->assertRedirect();

        $this->post(app(LearnerLinks::class)->profile($second), [
            'id_type' => 'sa_id', 'id_number' => self::VALID_SA_ID,
        ])->assertSessionHasErrors('id_number');

        $this->assertNull($second->fresh()->id_number_hash);
    }

    public function test_the_friendly_link_points_at_the_website_not_the_admin_path(): void
    {
        config(['app.url' => 'https://www.kcs.edu.za/admin', 'kcs.public_url' => 'https://www.kcs.edu.za']);
        URL::forceRootUrl('https://www.kcs.edu.za/admin');

        $link = app(LearnerLinks::class)->friendlyProfile($this->learner());

        // A link that reads like a staff area is a link people do not click.
        $this->assertStringStartsWith('https://www.kcs.edu.za/my/profile/', $link);
        $this->assertStringNotContainsString('/admin/', $link);
        $this->assertStringContainsString('signature=', $link);
    }

    private function learner(string $first = 'Thabiso', string $last = 'Mokoena', string $email = 'thabiso@example.co.za'): Learner
    {
        $learner = Learner::create([
            'learner_ref' => 'NAL-2026-'.str_pad((string) (Learner::count() + 1), 5, '0', STR_PAD_LEFT),
            'first_registered_year' => 2026,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
        ]);

        Application::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', 'PPO')->value('id'),
            'source' => ApplicationSource::FLUENTFORM,
            'status' => ApplicationStatus::PAID,
            'applied_at' => now(),
        ]);

        return $learner;
    }
}
