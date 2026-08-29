<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * One ladder from first contact to a registered student.
 *
 * This is deliberately not an "application" pipeline. A person is not asked to
 * apply and wait; they register, they pay, and the detail is collected after
 * their place is held. The stages exist so a Facebook lead, a website
 * registration and a walk-in are the same record at different heights on the
 * same ladder rather than three systems that have to be reconciled.
 *
 * Two rungs deliberately live elsewhere. "Active student" is a property of the
 * learner, not of this registration — it is true once a token has actually been
 * redeemed in the app. And profile completeness is computed from the learner
 * record by ProfileCompleteness rather than stored here, so it cannot drift out
 * of step with the fields it describes; profile_incomplete is only the parking
 * state that makes the gap visible in the queue.
 */
enum ApplicationStatus: string
{
    /** Captured from a campaign or referral. Nobody has spoken to them yet. */
    case LEAD = 'lead';

    /** Someone has made contact. Still no registration of their own. */
    case CONTACTED = 'contacted';

    /** They gave a name, a contact and a programme. The record is theirs now. */
    case REGISTRATION_STARTED = 'registration_started';

    /** Accepted against an offering; the invoices exist and are owed. */
    case AWAITING_PAYMENT = 'awaiting_payment';

    /** Money received. Access follows from here, not from paperwork. */
    case PAID = 'paid';

    /** Paid and studying, with detail still owed — identity, education, documents. */
    case PROFILE_INCOMPLETE = 'profile_incomplete';

    /** Everything required is on file. */
    case REGISTERED = 'registered';

    case WITHDRAWN = 'withdrawn';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::LEAD => 'New lead',
            self::CONTACTED => 'Contacted',
            self::REGISTRATION_STARTED => 'Registration started',
            self::AWAITING_PAYMENT => 'Awaiting payment',
            self::PAID => 'Paid',
            self::PROFILE_INCOMPLETE => 'Profile incomplete',
            self::REGISTERED => 'Registered',
            self::WITHDRAWN => 'Withdrawn',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * How far up the ladder this is, for sorting and for reporting where people
     * fall out. Ended states sit outside the sequence rather than at the top of
     * it — a withdrawal is not progress.
     */
    public function stage(): int
    {
        return match ($this) {
            self::LEAD => 1,
            self::CONTACTED => 2,
            self::REGISTRATION_STARTED => 3,
            self::AWAITING_PAYMENT => 4,
            self::PAID => 5,
            self::PROFILE_INCOMPLETE => 6,
            self::REGISTERED => 7,
            self::WITHDRAWN, self::REJECTED => 0,
        };
    }

    /** Still in play — neither finished nor abandoned. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::REGISTERED, self::WITHDRAWN, self::REJECTED], true);
    }

    /** Before any money has been committed on either side. */
    public function isPreRegistration(): bool
    {
        return in_array($this, [self::LEAD, self::CONTACTED], true);
    }

    /** The registration is theirs and can be accepted against an offering. */
    public function isDecidable(): bool
    {
        return in_array($this, [self::LEAD, self::CONTACTED, self::REGISTRATION_STARTED], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
