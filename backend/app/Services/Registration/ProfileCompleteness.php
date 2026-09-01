<?php

declare(strict_types=1);

namespace App\Services\Registration;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Learner;

/**
 * What a learner still owes us, after they have paid.
 *
 * The registration deliberately asks for almost nothing up front — a name, a
 * way to reach them, and which programme. Everything else is collected once
 * their place is held. That trade only works if the gap is visible: this turns
 * the empty fields on a learner record into a percentage anyone can read and a
 * list someone can actually chase.
 *
 * It is computed, never stored. A stored percentage drifts the first time a
 * field is added; this one cannot.
 */
class ProfileCompleteness
{
    /**
     * The fields, in the order they are worth chasing. Identity leads because
     * no access token is issued without it — it is the one that actually stops
     * a paid learner from starting.
     *
     * @var array<string, array{label: string, blocking: bool}>
     */
    private const REQUIRED = [
        'identity' => ['label' => 'Identity document', 'blocking' => true],
        'date_of_birth' => ['label' => 'Date of birth', 'blocking' => false],
        'phone' => ['label' => 'Mobile number', 'blocking' => false],
        'email' => ['label' => 'Email address', 'blocking' => false],
        'address_line' => ['label' => 'Home address', 'blocking' => false],
        'city' => ['label' => 'Town or city', 'blocking' => false],
        'province' => ['label' => 'Province', 'blocking' => false],
        'highest_qualification' => ['label' => 'Highest qualification', 'blocking' => false],
        'school_or_institution' => ['label' => 'School or institution', 'blocking' => false],
        'employment_status' => ['label' => 'Employment status', 'blocking' => false],
    ];

    /**
     * @return array<int, string> The labels of everything still outstanding.
     */
    public function missing(Learner $learner): array
    {
        $missing = [];

        foreach (self::REQUIRED as $field => $meta) {
            if (! $this->has($learner, $field)) {
                $missing[] = $meta['label'];
            }
        }

        return $missing;
    }

    /**
     * Outstanding items that stop the learner getting into the app, as opposed
     * to items that merely stop the file being complete.
     *
     * @return array<int, string>
     */
    public function blocking(Learner $learner): array
    {
        $blocking = [];

        foreach (self::REQUIRED as $field => $meta) {
            if ($meta['blocking'] && ! $this->has($learner, $field)) {
                $blocking[] = $meta['label'];
            }
        }

        return $blocking;
    }

    /** 0–100, rounded down so 99% never reads as finished. */
    public function percent(Learner $learner): int
    {
        $total = count(self::REQUIRED);
        $done = $total - count($this->missing($learner));

        return (int) floor($done / $total * 100);
    }

    public function isComplete(Learner $learner): bool
    {
        return $this->missing($learner) === [];
    }

    /**
     * Stamp the record when it first becomes complete, and unstamp it if a
     * field is later cleared. Returns whether the learner is now complete.
     */
    public function refresh(Learner $learner): bool
    {
        $complete = $this->isComplete($learner);

        if ($complete && $learner->profile_completed_at === null) {
            $learner->forceFill(['profile_completed_at' => now()])->save();
        }

        if (! $complete && $learner->profile_completed_at !== null) {
            $learner->forceFill(['profile_completed_at' => null])->save();
        }

        return $complete;
    }

    /**
     * Put the application on the rung the learner's file now warrants.
     *
     * Registered when nothing is outstanding, profile_incomplete while
     * something is. Shared by the two paths that can change it — a payment
     * settling, and the learner filling in their own details from the secure
     * link — so the rule lives here rather than being restated in each.
     */
    public function settleApplication(?Application $application, Learner $learner): bool
    {
        $complete = $this->refresh($learner);

        $application?->update($complete
            ? ['status' => ApplicationStatus::REGISTERED, 'registered_at' => now()]
            : ['status' => ApplicationStatus::PROFILE_INCOMPLETE, 'registered_at' => null]);

        return $complete;
    }

    private function has(Learner $learner, string $field): bool
    {
        if ($field === 'identity') {
            // A number on file is not enough. A passport still needs a person
            // to have sighted the document, which is what verification means.
            return $learner->hasVerifiedIdentity();
        }

        $value = $learner->getAttribute($field);

        return $value !== null && trim((string) $value) !== '';
    }
}
