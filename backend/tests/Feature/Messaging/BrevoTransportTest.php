<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * The Brevo transport, which is the only sender that passes SPF for this
 * domain — kcs.edu.za publishes `include:_spf.google.com ~all`, so mail
 * relayed through the cPanel server is spam-foldered.
 *
 * Asserted against the shape Brevo's API actually requires, because a payload
 * it rejects is indistinguishable from a mail server being down until
 * somebody reads the log.
 */
class BrevoTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'brevo',
            'mail.mailers.brevo.key' => 'xkeysib-test-key',
            'mail.from.address' => 'admissions@kcs.edu.za',
            'mail.from.name' => 'Katlehong Computer School',
        ]);
    }

    public function test_it_posts_the_message_to_brevo_in_the_shape_the_api_expects(): void
    {
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<abc@brevo>'], 201)]);

        Mail::raw('Your reference is NAL-2026-00001.', function ($message): void {
            $message->to('learner@example.co.za')->subject('We have your registration');
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'xkeysib-test-key')
                && $request['sender']['email'] === 'admissions@kcs.edu.za'
                && $request['sender']['name'] === 'Katlehong Computer School'
                && $request['to'][0]['email'] === 'learner@example.co.za'
                && $request['subject'] === 'We have your registration'
                && str_contains((string) $request['textContent'], 'NAL-2026-00001');
        });
    }

    public function test_a_refusal_from_brevo_is_raised_rather_than_swallowed(): void
    {
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(
            ['message' => 'Sender not valid'], 400,
        )]);

        // The transport reports; LearnerMailer is the layer that decides a
        // failed message must not break a registration. Swallowing it here
        // would leave nothing to log.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Brevo refused the message.*Sender not valid/s');

        Mail::raw('body', fn ($m) => $m->to('learner@example.co.za')->subject('x'));
    }

    public function test_html_and_text_both_travel_when_a_mailable_has_both(): void
    {
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<x>'], 201)]);

        Mail::html('<p>Pay <strong>R500</strong> at any Pay@ till.</p>', function ($message): void {
            $message->to('learner@example.co.za')->subject('Your payment reference');
        });

        Http::assertSent(fn (Request $r): bool => str_contains((string) $r['htmlContent'], 'R500'));
    }
}
