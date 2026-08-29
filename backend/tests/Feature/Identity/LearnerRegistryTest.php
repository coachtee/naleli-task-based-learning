<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Enums\IdType;
use App\Models\Learner;
use App\Models\LearnerIdentifier;
use App\Services\Identity\LearnerRegistry;
use App\Support\SouthAfricanId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * "One learner, one ID for life" is the property the whole backend rests on,
 * so it is the first thing tested. Everything here is about two failures that
 * would be expensive to discover later: the same person becoming two learners,
 * and one reference being issued twice.
 */
class LearnerRegistryTest extends TestCase
{
    use RefreshDatabase;

    private LearnerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(LearnerRegistry::class);
    }

    public function test_it_allocates_a_reference_in_the_agreed_format(): void
    {
        $learner = $this->registry->resolve([
            'first_name' => 'Thabiso',
            'last_name' => 'Mokoena',
            'email' => 'thabiso@example.co.za',
        ]);

        $year = (int) date('Y');

        $this->assertSame("NAL-{$year}-00001", $learner->learner_ref);
        $this->assertSame($year, $learner->first_registered_year);
    }

    public function test_references_increment_and_never_repeat(): void
    {
        $refs = [];

        for ($i = 1; $i <= 25; $i++) {
            $refs[] = $this->registry->resolve([
                'first_name' => "Learner{$i}",
                'last_name' => 'Test',
                'email' => "learner{$i}@example.co.za",
            ])->learner_ref;
        }

        $this->assertCount(25, array_unique($refs), 'every reference must be distinct');
        $this->assertSame(sprintf('NAL-%d-00025', (int) date('Y')), end($refs));
    }

    public function test_a_repeat_application_from_the_same_email_reuses_the_learner(): void
    {
        $first = $this->registry->resolve([
            'first_name' => 'Nomsa',
            'last_name' => 'Dlamini',
            'email' => 'Nomsa@Example.co.za  ',
        ]);

        $second = $this->registry->resolve([
            'first_name' => 'Nomsa',
            'last_name' => 'Dlamini',
            'email' => 'nomsa@example.co.za',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Learner::count());
    }

    public function test_a_learner_who_changed_email_still_resolves_by_the_old_one(): void
    {
        $learner = $this->registry->resolve([
            'first_name' => 'Sipho',
            'last_name' => 'Ndlovu',
            'email' => 'sipho.old@example.co.za',
        ]);

        $learner->update(['email' => 'sipho.new@example.co.za']);

        $again = $this->registry->resolve([
            'first_name' => 'Sipho',
            'last_name' => 'Ndlovu',
            'email' => 'sipho.old@example.co.za',
        ]);

        $this->assertSame($learner->id, $again->id);
    }

    public function test_a_local_phone_number_matches_its_international_form(): void
    {
        $first = $this->registry->resolve([
            'first_name' => 'Lerato',
            'last_name' => 'Khumalo',
            'phone' => '082 123 4567',
        ]);

        $second = $this->registry->resolve([
            'first_name' => 'Lerato',
            'last_name' => 'Khumalo',
            'phone' => '+27821234567',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('+27821234567', $first->fresh()->phone);
    }

    public function test_the_id_number_outranks_a_changed_email(): void
    {
        $id = $this->validSaIdNumber();

        $first = $this->registry->resolve([
            'first_name' => 'Kagiso',
            'last_name' => 'Sithole',
            'email' => 'kagiso@example.co.za',
            'id_type' => 'sa_id',
            'id_number' => $id,
        ]);

        // Applies again months later from a completely different address.
        $second = $this->registry->resolve([
            'first_name' => 'Kagiso',
            'last_name' => 'Sithole',
            'email' => 'kagiso.work@другой.example',
            'id_type' => 'sa_id',
            'id_number' => $id,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Learner::count());
    }

    public function test_two_different_people_get_two_learners_and_two_references(): void
    {
        $a = $this->registry->resolve([
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'email' => 'thabo.one@example.co.za',
        ]);

        $b = $this->registry->resolve([
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'email' => 'thabo.two@example.co.za',
        ]);

        $this->assertNotSame($a->id, $b->id);
        $this->assertNotSame($a->learner_ref, $b->learner_ref);
    }

    public function test_a_valid_sa_id_is_stored_encrypted_hashed_masked_and_verified(): void
    {
        $id = $this->validSaIdNumber();

        $learner = $this->registry->resolve([
            'first_name' => 'Palesa',
            'last_name' => 'Motaung',
            'email' => 'palesa@example.co.za',
            'id_type' => 'sa_id',
            'id_number' => $id,
        ]);

        $this->assertSame(IdType::SA_ID, $learner->id_type);
        $this->assertSame(hash('sha256', $id), $learner->id_number_hash);
        $this->assertSame($id, $learner->id_number_encrypted, 'the cast decrypts transparently');
        $this->assertNotNull($learner->identity_verified_at);
        $this->assertNotNull($learner->date_of_birth);

        // The raw number must never sit in the column in clear.
        $stored = \DB::table('learners')->where('id', $learner->id)->value('id_number_encrypted');
        $this->assertNotSame($id, $stored);
        $this->assertStringNotContainsString($id, (string) $stored);

        // Lists show enough to recognise, not enough to use.
        $this->assertStringContainsString('•', $learner->id_number_masked);
        $this->assertStringEndsWith(substr($id, -3), $learner->id_number_masked);
    }

    public function test_an_invalid_sa_id_is_rejected_rather_than_stored(): void
    {
        $this->expectException(RuntimeException::class);

        $this->registry->resolve([
            'first_name' => 'Bad',
            'last_name' => 'Digits',
            'email' => 'bad@example.co.za',
            'id_type' => 'sa_id',
            'id_number' => '9001015800086',   // last digit wrong
        ]);
    }

    public function test_a_passport_is_stored_but_not_auto_verified(): void
    {
        $learner = $this->registry->resolve([
            'first_name' => 'Grace',
            'last_name' => 'Banda',
            'email' => 'grace@example.co.za',
            'id_type' => 'passport',
            'id_number' => 'ZN1234567',
        ]);

        $this->assertSame(IdType::PASSPORT, $learner->id_type);
        $this->assertNotNull($learner->id_number_hash);
        $this->assertNull(
            $learner->identity_verified_at,
            'a passport needs a human to sight the document',
        );
    }

    public function test_identification_supplied_later_fills_in_without_creating_a_second_learner(): void
    {
        $learner = $this->registry->resolve([
            'first_name' => 'Andile',
            'last_name' => 'Zulu',
            'email' => 'andile@example.co.za',
        ]);

        $this->assertNull($learner->id_number_hash);

        $again = $this->registry->resolve([
            'first_name' => 'Andile',
            'last_name' => 'Zulu',
            'email' => 'andile@example.co.za',
            'id_type' => 'sa_id',
            'id_number' => $this->validSaIdNumber(),
        ]);

        $this->assertSame($learner->id, $again->id);
        $this->assertNotNull($again->id_number_hash);
        $this->assertSame(1, Learner::count());
    }

    public function test_every_contact_key_is_recorded_for_future_matching(): void
    {
        $learner = $this->registry->resolve([
            'first_name' => 'Zanele',
            'last_name' => 'Nkosi',
            'email' => 'zanele@example.co.za',
            'phone' => '0731234567',
        ]);

        $this->assertSame(2, LearnerIdentifier::where('learner_id', $learner->id)->count());
    }

    /**
     * A structurally valid SA ID: 1 January 1990, sequence 5800, citizen,
     * with a correct Luhn check digit. Verified by the parser itself so the
     * fixture cannot silently rot.
     */
    private function validSaIdNumber(): string
    {
        $id = '9001015800088';

        $this->assertTrue(
            SouthAfricanId::isValid($id),
            'the test fixture must itself be a valid SA ID number',
        );

        return $id;
    }
}
