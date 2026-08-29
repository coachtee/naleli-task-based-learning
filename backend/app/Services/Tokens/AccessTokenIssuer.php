<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use App\Enums\TokenStatus;
use App\Models\AccessToken;
use App\Models\Enrolment;
use App\Models\User;
use DomainException;

/**
 * Issues programme access.
 *
 * A token is not the learner's identity — it grants access to one enrolment.
 * The same person collects a second token for a second programme and opens
 * both in the same app under the same permanent reference, which is the whole
 * point of keeping identity and access apart.
 */
class AccessTokenIssuer
{
    /**
     * Crockford base32 without I, L, O or U: learners read these off a printed
     * letter and type them on a phone, and those four are the characters that
     * get mistaken for 1, 1, 0 and V.
     */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const BLOCKS = 3;

    private const BLOCK_LENGTH = 4;

    /**
     * @return array{token: AccessToken, plain: string} the plain value is
     *                                                  returned once and never recoverable afterwards
     */
    public function issue(Enrolment $enrolment, ?User $issuedBy = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $learner = $enrolment->learner;

        // The identity gate. Identification is optional at application and
        // required here, because the learner reference only starts to mean
        // something once the learner begins producing evidence toward a
        // qualification — and that begins at activation.
        if (! $learner->hasVerifiedIdentity()) {
            throw new DomainException(
                "Cannot issue a token for {$learner->learner_ref}: identity is not verified.",
            );
        }

        $plain = $this->generatePlainToken();

        $token = AccessToken::create([
            'learner_id' => $learner->id,
            'enrolment_id' => $enrolment->id,
            'token_hash' => $this->hash($plain),
            'token_prefix' => substr($plain, 0, 8),
            'status' => TokenStatus::ISSUED,
            'issued_at' => now(),
            'issued_by' => $issuedBy?->id,
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    /** Redeem a plain token, binding it to whoever presented it. */
    public function redeem(string $plain): ?AccessToken
    {
        $token = AccessToken::where('token_hash', $this->hash($this->canonicalise($plain)))->first();

        if ($token === null || ! $token->isRedeemable()) {
            return null;
        }

        $token->update([
            'status' => TokenStatus::ACTIVE,
            'activated_at' => now(),
        ]);

        return $token->refresh();
    }

    public function revoke(AccessToken $token, string $reason): AccessToken
    {
        $token->update([
            'status' => TokenStatus::REVOKED,
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ]);

        return $token->refresh();
    }

    public function hash(string $plain): string
    {
        return hash('sha256', $this->canonicalise($plain));
    }

    /**
     * Accepts what a learner actually types: spaces, lower case, missing
     * dashes. Rejecting a correct token because it was typed without hyphens
     * would be a support call for nothing.
     */
    public function canonicalise(string $plain): string
    {
        $stripped = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plain) ?? '');

        if (str_starts_with($stripped, 'KCS')) {
            $stripped = substr($stripped, 3);
        }

        return 'KCS-'.implode('-', str_split($stripped, self::BLOCK_LENGTH));
    }

    private function generatePlainToken(): string
    {
        $blocks = [];

        for ($b = 0; $b < self::BLOCKS; $b++) {
            $block = '';

            for ($i = 0; $i < self::BLOCK_LENGTH; $i++) {
                $block .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $blocks[] = $block;
        }

        return 'KCS-'.implode('-', $blocks);
    }
}
