<?php

declare(strict_types=1);

namespace Tests\Feature\Flow;

use App\Enums\EnrolmentStatus;
use App\Mail\CourseAccessOpened;
use App\Mail\RegistrationReceived;
use App\Models\Application;
use App\Models\Learner;
use App\Models\Offering;
use App\Services\Enrolment\ApplicationAcceptor;
use App\Services\Enrolment\EnrolmentActivator;
use App\Services\Registration\LearnerLinks;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The whole journey, as the school's director wants to walk it:
 *
 *   fill in the form on the website
 *     -> the school sees the registration and accepts it
 *     -> the learner pays
 *     -> an email arrives telling them how to get in
 *     -> they choose a PIN
 *     -> they sign in at a lab computer and start studying
 *
 * Every link in that chain existed except the fourth. Activation minted an
 * access token for the phone app, the registrar read it off a screen, and
 * nobody ever told the learner how to reach the workspace. A journey that
 * dead-ends one step after the money is the worst place to have a gap, so it
 * is asserted here end to end rather than in pieces.
 */
class ProspectToStudentTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_SA_ID = '9001015800088';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        config([
            'webhooks.fluentform.secret' => 'test-secret',
            'kcs.workspace_url' => 'https://www.kcs.edu.za/workspace',
        ]);
    }

    public function test_a_prospect_becomes_a_student_who_can_sign_in_at_the_lab(): void
    {
        Mail::fake();

        // 1 — the prospect fills in the form on kcs.edu.za.
        $this->postSignedApplication()->assertCreated();

        $learner = Learner::firstOrFail();
        $application = Application::firstOrFail();

        Mail::assertSent(RegistrationReceived::class);

        // 2 — the registrar accepts it against what the school sells.
        $enrolment = app(ApplicationAcceptor::class)->accept(
            $application,
            Offering::where('code', 'DOPF-2027')->firstOrFail(),
        );

        // 3 — the learner pays the registration fee.
        $result = app(EnrolmentActivator::class)->confirmInvoiceManually(
            invoice: $enrolment->activatingInvoice(),
            providerKey: 'payat_go',
            reference: 'PAYAT-UAT-0001',
        );

        $this->assertSame(EnrolmentStatus::ACTIVE, $enrolment->fresh()->status);

        // 4 — the gap that used to be here. An email arrives, and it carries a
        //     link rather than a PIN: a credential in an inbox outlives the
        //     course, a signed link does not.
        Mail::assertSent(CourseAccessOpened::class, function (CourseAccessOpened $mail) use ($learner): bool {
            $rendered = $mail->render();

            return str_contains($rendered, $learner->learner_ref)
                && str_contains($rendered, '/my/start/')
                && ! str_contains($rendered, 'PIN is');
        });

        // 5 — the learner opens the link and chooses a PIN.
        $link = app(LearnerLinks::class)->workspaceAccess($learner);

        $this->get($link)
            ->assertOk()
            ->assertSee('Choose your PIN', false)
            ->assertSee($learner->learner_ref);

        $this->followingRedirects()
            ->post($link, ['pin' => '481907', 'pin_confirmation' => '481907'])
            ->assertOk()
            ->assertSee('Your PIN is saved', false)
            ->assertSee('https://www.kcs.edu.za/workspace', false);

        $this->assertNotNull($learner->fresh()->pin_hash);

        // 6 — and signs in at a lab computer with the number and the PIN.
        $session = $this->postJson('/api/v1/sessions', [
            'learner_ref' => $learner->learner_ref,
            'pin' => '481907',
        ])->assertCreated();

        $this->app['auth']->forgetGuards();

        $record = $this->withHeader('Authorization', 'Bearer '.$session->json('token'))
            ->getJson('/api/v1/me/progress?programme=DOPF')
            ->assertOk();

        // The Foundation is the one programme whose content is written, so a
        // learner who has just paid for it can actually start today.
        $record->assertJsonPath('programme.content_code', 'digital-foundation');
        $this->assertSame([], $record->json('sub_steps'), 'a brand new student starts empty');

        // And the phone gets its own front door from the same email.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/tokens/activate', [
            'token' => $result['plain_token'],
            'device_name' => 'Learner phone',
        ])->assertCreated();
    }

    public function test_the_link_is_the_credential_and_refuses_to_be_edited(): void
    {
        $mine = $this->paidLearner();

        // Created directly: this is a test about a tampered link, and routing
        // a second learner through the whole intake only risks the two being
        // silently deduped into one — which would make the tamper assertion
        // pass against itself.
        $someoneElse = Learner::create([
            'learner_ref' => 'NAL-2026-09000',
            'first_registered_year' => 2026,
            'first_name' => 'Naledi',
            'last_name' => 'Dlamini',
            'email' => 'naledi@example.co.za',
        ]);

        $this->assertNotSame($mine->id, $someoneElse->id);

        // Unsigned.
        $this->get("/my/start/{$mine->id}")->assertForbidden();

        // Signed for me, pointed at somebody else.
        $tampered = str_replace(
            "/my/start/{$mine->id}?",
            "/my/start/{$someoneElse->id}?",
            app(LearnerLinks::class)->workspaceAccess($mine),
        );
        $this->get($tampered)->assertForbidden();

        // Expired.
        $this->travel(LearnerLinks::ACCESS_DAYS + 1)->days();
        $this->get(app(LearnerLinks::class)->workspaceAccess($mine, days: -1))->assertForbidden();
    }

    public function test_a_pin_anybody_could_guess_is_refused(): void
    {
        $learner = $this->paidLearner();
        $link = app(LearnerLinks::class)->workspaceAccess($learner);

        foreach (['000000', '111111', '123456', '654321'] as $obvious) {
            $this->post($link, ['pin' => $obvious, 'pin_confirmation' => $obvious])
                ->assertSessionHasErrors('pin');
        }

        $this->post($link, ['pin' => '481907', 'pin_confirmation' => '481906'])
            ->assertSessionHasErrors('pin');

        $this->assertNull($learner->fresh()->pin_hash, 'nothing was set by any of those');
    }

    public function test_setting_a_new_pin_stops_the_old_one_at_once(): void
    {
        $learner = $this->paidLearner();
        $link = app(LearnerLinks::class)->workspaceAccess($learner);

        $this->post($link, ['pin' => '481907', 'pin_confirmation' => '481907'])->assertRedirect();
        $this->postJson('/api/v1/sessions', ['learner_ref' => $learner->learner_ref, 'pin' => '481907'])
            ->assertCreated();

        // A learner who thinks somebody watched them type it asks for a new link.
        $this->get($link)->assertOk()->assertSee('You already have a PIN', false);
        $this->post($link, ['pin' => '203948', 'pin_confirmation' => '203948'])->assertRedirect();

        $this->postJson('/api/v1/sessions', ['learner_ref' => $learner->learner_ref, 'pin' => '481907'])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------- helpers

    private function paidLearner(
        string $first = 'Thabiso',
        string $last = 'Mokoena',
        string $email = 'thabiso@example.co.za',
        string $id = self::VALID_SA_ID,
    ): Learner {
        Mail::fake();
        $this->postSignedApplication($first, $last, $email, $id)->assertCreated();

        $application = Application::where('id', '>', 0)->latest('id')->firstOrFail();
        $enrolment = app(ApplicationAcceptor::class)->accept(
            $application,
            Offering::where('code', 'DOPF-2027')->firstOrFail(),
        );

        app(EnrolmentActivator::class)->confirmInvoiceManually(
            invoice: $enrolment->activatingInvoice(),
            providerKey: 'manual',
            reference: 'PAID-'.$application->id,
        );

        $learner = $application->learner->fresh();

        // Guards the trap this helper fell into once: a second call that gets
        // deduped back onto the first learner makes every "somebody else"
        // assertion pass against itself.
        $this->assertSame($email, $learner->email);

        return $learner;
    }

    private function postSignedApplication(
        string $first = 'Thabiso',
        string $last = 'Mokoena',
        string $email = 'thabiso@example.co.za',
        string $id = self::VALID_SA_ID,
    ): TestResponse {
        $payload = [
            'source' => 'fluentform',
            'form_id' => 8,
            'submission_id' => random_int(1000, 999999),
            'submitted_at' => '2027-01-14T09:12:00+02:00',
            'applicant' => [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => '082 123 4567',
                'id_type' => 'sa_id',
                'id_number' => $id,
            ],
            'programme_code' => 'DOPF',
            'enrolment_plan' => 'monthly',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/v1/intake/application', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_KCS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
        ], content: $body);
    }
}
