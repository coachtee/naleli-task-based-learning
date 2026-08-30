<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Enums\ApplicationStatus;
use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Models\Application;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The exact shape the website posts.
 *
 * Form 15 sends the programme's full name rather than a code, because that is
 * what a learner picks from the dropdown. These assert that the name resolves,
 * that the funding answer lands in its own column rather than only inside the
 * stored payload, and that the campaign is kept so a Facebook lead can be told
 * apart from a website registration.
 */
class RegistrationIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        config(['webhooks.fluentform.secret' => 'test-secret']);
    }

    public function test_a_registration_from_the_website_lands_against_the_right_programme(): void
    {
        $this->postSigned($this->payload())->assertCreated();

        $application = Application::firstOrFail();

        $this->assertSame('PPO', $application->programme->code);
        $this->assertSame(ApplicationStatus::REGISTRATION_STARTED, $application->status);
        $this->assertSame('Thandi', $application->learner->first_name);
        $this->assertMatchesRegularExpression('/^NAL-\d{4}-\d{5}$/', $application->learner->learner_ref);
        $this->assertSame('+27821234567', $application->learner->whatsapp);
    }

    public function test_the_funding_answer_lands_in_its_own_column(): void
    {
        $this->postSigned($this->payload(['funding_source' => 'Applying for funding']))->assertCreated();

        $application = Application::firstOrFail();

        $this->assertSame(FundingSource::FUNDING_APPLICATION, $application->funding_source);
        $this->assertSame(FundingStatus::PENDING, $application->funding_status);
        $this->assertTrue($application->hasOpenFundingMatter());
    }

    public function test_paying_for_yourself_raises_no_funding_matter(): void
    {
        $this->postSigned($this->payload(['funding_source' => 'I am paying for myself']))->assertCreated();

        $application = Application::firstOrFail();

        $this->assertSame(FundingSource::SELF, $application->funding_source);
        $this->assertSame(FundingStatus::NOT_REQUIRED, $application->funding_status);
        $this->assertFalse($application->hasOpenFundingMatter());
    }

    public function test_the_campaign_is_kept_so_a_lead_can_be_told_from_a_registration(): void
    {
        $this->postSigned($this->payload(['campaign' => 'facebook-february-intake']))->assertCreated();

        $this->assertSame('facebook-february-intake', Application::firstOrFail()->campaign);
    }

    public function test_every_programme_on_the_form_resolves(): void
    {
        $names = array_keys(config('webhooks.fluentform.programme_map'));

        foreach ($names as $i => $name) {
            $this->postSigned($this->payload([
                'programme_code' => $name,
                'submission_id' => 1000 + $i,
                'applicant_email' => "applicant{$i}@example.co.za",
            ]))->assertCreated();
        }

        $this->assertSame(
            count($names),
            Application::whereNotNull('programme_id')->count(),
            'A programme the form offers did not resolve to a catalogue entry.',
        );
    }

    public function test_an_unsigned_post_is_refused(): void
    {
        $this->postJson('/api/v1/intake/application', ['source' => 'fluentform'])
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        $email = $overrides['applicant_email'] ?? 'thandi.mokoena@example.co.za';
        unset($overrides['applicant_email']);

        return array_merge([
            'source' => 'fluentform',
            'form_id' => 15,
            'submission_id' => 501,
            'submitted_at' => '2026-08-30T09:00:00+02:00',
            'applicant' => [
                'first_name' => 'Thandi',
                'last_name' => 'Mokoena',
                'email' => $email,
                'phone' => '082 123 4567',
                'whatsapp' => '082 123 4567',
            ],
            'programme_code' => 'People & Payroll Operations',
            'funding_source' => 'I am paying for myself',
            'campaign' => 'website',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->call(
            method: 'POST',
            uri: '/api/v1/intake/application',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_KCS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
            ],
            content: $body,
        );
    }
}
