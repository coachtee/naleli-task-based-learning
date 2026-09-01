<?php

declare(strict_types=1);

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends through Brevo's HTTP API rather than SMTP.
 *
 * The domain's MX and SPF both point at Google — `include:_spf.google.com ~all`
 * — so anything relayed through the cPanel server fails SPF and lands in spam.
 * Brevo is the sender that already passes for kcs.edu.za, because the website
 * has been sending through it for months.
 *
 * The API rather than SMTP relay because the credential we already hold is a
 * v3 API key (`xkeysib-…`); Brevo's SMTP relay wants a different one. Using
 * the API means the backend and the website authenticate as the same sender
 * with the same secret, and nobody has to go and mint a second credential.
 *
 * Written directly rather than pulling in symfony/brevo-mailer: it is one
 * HTTP call, and the payload below is the whole integration.
 */
class BrevoApiTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 20,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])->timeout($this->timeout)->post(self::ENDPOINT, $this->payload($email));

        if ($response->failed()) {
            // Surfaced rather than swallowed: LearnerMailer is the layer that
            // decides a failed message must not break a registration, and it
            // can only log what went wrong if the transport says so.
            throw new \RuntimeException(
                'Brevo refused the message: HTTP '.$response->status().' '.$response->body(),
            );
        }

        // Brevo's own id for the message, so a bounce can be traced back.
        $message->setMessageId((string) ($response->json('messageId') ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Email $email): array
    {
        $from = $email->getFrom()[0] ?? null;

        $payload = [
            'sender' => $this->address($from),
            'to' => array_map($this->address(...), $email->getTo()),
            'subject' => $email->getSubject() ?? '',
        ];

        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = (string) $email->getHtmlBody();
        }

        if ($email->getTextBody() !== null) {
            $payload['textContent'] = (string) $email->getTextBody();
        }

        // Brevo rejects a message with neither body, and a mailable that
        // renders to nothing is a bug worth seeing rather than silently
        // delivering an empty email.
        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            $payload['textContent'] = ' ';
        }

        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $field => $addresses) {
            if ($addresses !== []) {
                $payload[$field] = array_map($this->address(...), $addresses);
            }
        }

        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $this->address($email->getReplyTo()[0]);
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function address(?Address $address): array
    {
        if ($address === null) {
            return [];
        }

        $out = ['email' => $address->getAddress()];

        if ($address->getName() !== '') {
            $out['name'] = $address->getName();
        }

        return $out;
    }

    public function __toString(): string
    {
        return 'brevo+api://';
    }
}
