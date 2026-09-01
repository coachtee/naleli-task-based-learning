<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Mail\RegistrationReceived;
use App\Models\Application;
use App\Models\Learner;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "I registered and I didn't get anything on my email."
 *
 * The school's own director found this by registering on their own website.
 * A prospect who submits a form into silence has no evidence the school is
 * running, so the confirmation goes out the moment the form lands.
 *
 * The other half of what is asserted here matters more: a registration is
 * money, and it must survive email being broken. Every failure mode of the
 * mailer is tested for the same outcome — the learner record still exists.
 */
class RegistrationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        config([
            'webhooks.fluentform.secret' => 'test-secret',
            'mail.mailers.smtp.username' => 'admissions@kcs.edu.za',
            'mail.mailers.smtp.password' => 'app-password',
        ]);
    }

    public function test_registering_sends_a_confirmation_carrying_the_reference(): void
    {
        Mail::fake();

        $this->register()->assertCreated();

        $learner = Learner::sole();

        Mail::assertSent(RegistrationReceived::class, function (RegistrationReceived $mail) use ($learner): bool {
            return $mail->hasTo($learner->email)
                && $mail->application->learner->is($learner);
        });
    }

    public function test_the_confirmation_names_the_reference_the_fee_and_what_happens_next(): void
    {
        Mail::fake();
        $this->register()->assertCreated();

        $rendered = (new RegistrationReceived(Application::sole()))->render();

        $this->assertStringContainsString(Learner::sole()->learner_ref, $rendered);
        $this->assertStringContainsString('People &amp; Payroll Operations', $rendered);
        $this->assertStringContainsString('R500', $rendered);
        $this->assertStringContainsString('R950', $rendered);
        // Named tills, because "pay at Pay@" means nothing to somebody who has
        // never used one.
        $this->assertStringContainsString('Shoprite', $rendered);
    }

    public function test_a_mail_server_that_is_down_does_not_cost_the_registration(): void
    {
        // Nothing is faked: the mailer will genuinely fail to reach a host.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->register()->assertCreated();

        $this->assertSame(1, Learner::count(), 'the learner exists even though the email did not send');
        $this->assertSame(1, Application::count());
    }

    public function test_no_mail_credentials_means_no_attempt_rather_than_an_error(): void
    {
        config(['mail.mailers.smtp.username' => '', 'mail.mailers.smtp.password' => '']);
        Mail::fake();

        $this->register()->assertCreated();

        Mail::assertNothingSent();
        $this->assertSame(1, Learner::count());
    }

    public function test_a_registration_without_an_email_address_is_still_accepted(): void
    {
        Mail::fake();

        $payload = $this->payload();
        unset($payload['applicant']['email']);

        $this->register($payload)->assertCreated();

        Mail::assertNothingSent();
        $this->assertSame(1, Learner::count());
    }

    public function test_a_replayed_submission_does_not_email_twice(): void
    {
        Mail::fake();
        $payload = $this->payload();

        $this->register($payload)->assertCreated();
        $this->register($payload)->assertOk();

        // Fluent Forms retries deliveries; the learner must not be told twice.
        Mail::assertSentCount(1);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function register(?array $payload = null): TestResponse
    {
        $body = json_encode($payload ?? $this->payload(), JSON_THROW_ON_ERROR);

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

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'source' => 'fluentform',
            'form_id' => 15,
            'submission_id' => 91,
            'applicant' => [
                'first_name' => 'Thabiso',
                'last_name' => 'Mokoena',
                'email' => 'thabiso@example.co.za',
                'phone' => '082 123 4567',
                'whatsapp' => '082 123 4567',
            ],
            'programme_code' => 'PPO',
        ];
    }
}
