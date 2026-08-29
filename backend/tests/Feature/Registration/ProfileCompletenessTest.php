<?php

declare(strict_types=1);

namespace Tests\Feature\Registration;

use App\Enums\IdType;
use App\Enums\LearnerStatus;
use App\Models\Learner;
use App\Services\Registration\ProfileCompleteness;
use App\Support\Normalise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registration asks for almost nothing up front, which only works if what is
 * still owed stays visible afterwards. These assert that the gap is measured
 * from the record itself rather than from anyone's word for it.
 */
class ProfileCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_SA_ID = '9001015800088';

    public function test_a_registration_with_only_a_name_and_contact_is_barely_started(): void
    {
        $learner = $this->minimalLearner();
        $completeness = app(ProfileCompleteness::class);

        // Email and phone are all they gave, out of ten things we need.
        $this->assertSame(20, $completeness->percent($learner));
        $this->assertFalse($completeness->isComplete($learner));
        $this->assertContains('Identity document', $completeness->missing($learner));
        $this->assertContains('Highest qualification', $completeness->missing($learner));
    }

    public function test_identity_is_the_only_outstanding_item_that_blocks_access(): void
    {
        $learner = $this->minimalLearner();
        $completeness = app(ProfileCompleteness::class);

        $this->assertSame(['Identity document'], $completeness->blocking($learner));

        $this->verifyIdentity($learner);

        // Plenty still missing, but nothing that stops them studying.
        $this->assertSame([], $completeness->blocking($learner->fresh()));
        $this->assertNotSame([], $completeness->missing($learner->fresh()));
    }

    public function test_a_number_on_file_is_not_the_same_as_a_verified_identity(): void
    {
        $learner = $this->minimalLearner();

        // A passport recorded but never sighted by a person.
        $learner->update([
            'id_type' => IdType::PASSPORT,
            'id_number_encrypted' => 'A01234567',
            'id_number_hash' => Normalise::idHash('A01234567'),
            'id_number_masked' => 'A01••••567',
        ]);

        $this->assertSame(['Identity document'], app(ProfileCompleteness::class)->blocking($learner->fresh()));
    }

    public function test_a_full_record_reads_complete_and_is_stamped_once(): void
    {
        $learner = $this->minimalLearner();
        $this->verifyIdentity($learner);
        $this->fillTheRest($learner);

        $completeness = app(ProfileCompleteness::class);

        $this->assertSame(100, $completeness->percent($learner->fresh()));
        $this->assertTrue($completeness->refresh($learner->fresh()));

        $stamped = $learner->fresh()->profile_completed_at;
        $this->assertNotNull($stamped);

        // Running it again does not move the stamp.
        $completeness->refresh($learner->fresh());
        $this->assertEquals($stamped, $learner->fresh()->profile_completed_at);
    }

    public function test_clearing_a_field_takes_the_completion_stamp_back_off(): void
    {
        $learner = $this->minimalLearner();
        $this->verifyIdentity($learner);
        $this->fillTheRest($learner);

        $completeness = app(ProfileCompleteness::class);
        $completeness->refresh($learner->fresh());
        $this->assertNotNull($learner->fresh()->profile_completed_at);

        $learner->update(['province' => null]);

        $this->assertFalse($completeness->refresh($learner->fresh()));
        $this->assertNull($learner->fresh()->profile_completed_at);
    }

    private function minimalLearner(): Learner
    {
        // Exactly what the new registration asks for: a name, a way to reach
        // them, and nothing else.
        return Learner::create([
            'learner_ref' => 'NAL-2026-08001',
            'first_registered_year' => 2026,
            'first_name' => 'Naledi',
            'last_name' => 'Dlamini',
            'email' => 'naledi.dlamini@example.co.za',
            'phone' => '+27821234567',
            'whatsapp' => '+27821234567',
            'status' => LearnerStatus::APPLICANT,
        ]);
    }

    private function verifyIdentity(Learner $learner): void
    {
        $learner->update([
            'id_type' => IdType::SA_ID,
            'id_number_encrypted' => self::VALID_SA_ID,
            'id_number_hash' => Normalise::idHash(self::VALID_SA_ID),
            'id_number_masked' => Normalise::maskId(self::VALID_SA_ID),
            'date_of_birth' => '1990-01-01',
            'identity_verified_at' => now(),
        ]);
    }

    private function fillTheRest(Learner $learner): void
    {
        $learner->update([
            'address_line' => '14 Mothibe Street',
            'suburb' => 'Katlehong',
            'city' => 'Germiston',
            'province' => 'Gauteng',
            'postal_code' => '1431',
            'highest_qualification' => 'National Senior Certificate',
            'school_or_institution' => 'Katlehong Secondary School',
            'employment_status' => 'Unemployed, seeking work',
        ]);
    }
}
