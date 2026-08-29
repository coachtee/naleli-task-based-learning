<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\IdentifierType;
use App\Enums\IdType;
use App\Enums\LearnerStatus;
use App\Models\Learner;
use App\Models\LearnerIdentifier;
use App\Models\ReferenceCounter;
use App\Support\Normalise;
use App\Support\SouthAfricanId;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The one place a learner is created and the only place a learner reference is
 * allocated.
 *
 * "One learner, one ID for life" is only true if two things hold: the same
 * person resolves to the same row however they arrive, and a reference is
 * never issued twice. Both are enforced here rather than left to the caller,
 * which is why the webhook, the dashboard and any future import all go
 * through this class instead of touching Learner::create().
 */
class LearnerRegistry
{
    /**
     * Resolve an applicant to a learner, creating one if nobody matches.
     *
     * Matching runs strongest key first: a hashed ID number is definitive, a
     * normalised email is strong, a phone number is last because families and
     * internet cafés share them. Anything that does not match creates a new
     * learner with a freshly allocated reference.
     *
     * @param  array<string, mixed>  $applicant
     */
    public function resolve(array $applicant): Learner
    {
        return DB::transaction(function () use ($applicant) {
            $email = Normalise::email($applicant['email'] ?? null);
            $phone = Normalise::phone($applicant['phone'] ?? null);
            $idNumber = Normalise::idNumber($applicant['id_number'] ?? null);
            $idHash = $idNumber !== null ? Normalise::idHash($idNumber) : null;

            $learner = $this->findExisting($idHash, $email, $phone);

            if ($learner === null) {
                $learner = $this->create($applicant, $email, $phone);
            }

            $this->applyIdentification($learner, $applicant, $idNumber, $idHash);
            $this->recordIdentifiers($learner, $email, $phone);

            return $learner->refresh();
        });
    }

    private function findExisting(?string $idHash, ?string $email, ?string $phone): ?Learner
    {
        if ($idHash !== null) {
            $byId = Learner::where('id_number_hash', $idHash)->first();

            if ($byId !== null) {
                return $byId;
            }
        }

        foreach ([[IdentifierType::EMAIL, $email], [IdentifierType::PHONE, $phone]] as [$type, $value]) {
            if ($value === null) {
                continue;
            }

            $identifier = LearnerIdentifier::where('type', $type)
                ->where('value_normalised', $value)
                ->first();

            if ($identifier !== null) {
                return $identifier->learner;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $applicant
     */
    private function create(array $applicant, ?string $email, ?string $phone): Learner
    {
        $year = (int) date('Y');

        return Learner::create([
            'learner_ref' => $this->allocateReference($year),
            'first_registered_year' => $year,
            'first_name' => trim((string) ($applicant['first_name'] ?? '')),
            'middle_name' => $this->nullableString($applicant['middle_name'] ?? null),
            'last_name' => trim((string) ($applicant['last_name'] ?? '')),
            'email' => $email,
            'phone' => $phone,
            'whatsapp' => Normalise::phone($applicant['whatsapp'] ?? null),
            'status' => LearnerStatus::APPLICANT,
        ]);
    }

    /**
     * Allocate the next reference for a year: NAL-2026-00001.
     *
     * The counter row is locked for the duration of the surrounding
     * transaction, so two simultaneous applications queue rather than both
     * reading the same value. The unique index on learners.learner_ref is the
     * backstop — on a driver without real row locking the insert fails rather
     * than silently issuing a duplicate, and the caller retries.
     */
    public function allocateReference(int $year): string
    {
        $scope = "learner:{$year}";

        $counter = ReferenceCounter::query()
            ->where('scope', $scope)
            ->lockForUpdate()
            ->first();

        if ($counter === null) {
            ReferenceCounter::insertOrIgnore([
                'scope' => $scope,
                'next_value' => 1,
                'updated_at' => now(),
            ]);

            $counter = ReferenceCounter::query()
                ->where('scope', $scope)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $sequence = $counter->next_value;

        $counter->update([
            'next_value' => $sequence + 1,
            'updated_at' => now(),
        ]);

        return sprintf('NAL-%d-%05d', $year, $sequence);
    }

    /**
     * Identification is optional at application and required before a token is
     * issued, so this fills in what arrived and leaves the rest alone. It never
     * overwrites an ID already on file — a correction is a deliberate act by a
     * registrar, not a side effect of a second form submission.
     *
     * @param  array<string, mixed>  $applicant
     */
    private function applyIdentification(Learner $learner, array $applicant, ?string $idNumber, ?string $idHash): void
    {
        if ($idNumber === null || $learner->id_number_hash !== null) {
            return;
        }

        $type = $this->resolveIdType($applicant['id_type'] ?? null);

        $attributes = [
            'id_type' => $type,
            'id_number_encrypted' => $idNumber,
            'id_number_hash' => $idHash,
            'id_number_masked' => Normalise::maskId($idNumber),
        ];

        // Only an SA ID can be verified from the number itself. A passport or
        // permit needs a human to sight the document, so it stays unverified
        // until a registrar confirms it in the dashboard.
        if ($type === IdType::SA_ID) {
            $parsed = SouthAfricanId::parse($idNumber);

            if ($parsed === null) {
                throw new RuntimeException("Invalid South African ID number supplied for learner {$learner->learner_ref}.");
            }

            $attributes['date_of_birth'] = $parsed->dateOfBirth;
            $attributes['identity_verified_at'] = now();
        }

        $learner->update($attributes);
    }

    private function resolveIdType(mixed $raw): IdType
    {
        if ($raw instanceof IdType) {
            return $raw;
        }

        return IdType::tryFrom((string) $raw) ?? IdType::SA_ID;
    }

    /**
     * Keep every key that has ever pointed here, so a later application under
     * a former address still resolves to the same person.
     */
    private function recordIdentifiers(Learner $learner, ?string $email, ?string $phone): void
    {
        foreach ([[IdentifierType::EMAIL, $email], [IdentifierType::PHONE, $phone]] as [$type, $value]) {
            if ($value === null) {
                continue;
            }

            LearnerIdentifier::firstOrCreate(
                ['type' => $type, 'value_normalised' => $value],
                ['learner_id' => $learner->id],
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
