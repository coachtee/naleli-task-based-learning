<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Normalise;
use App\Support\SouthAfricanId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A mistyped ID number that passes validation becomes a duplicate learner
 * record for someone who already exists, and duplicates are far more expensive
 * to unpick than to prevent.
 */
class SouthAfricanIdTest extends TestCase
{
    public function test_a_correct_number_passes_and_yields_its_date_of_birth(): void
    {
        $parsed = SouthAfricanId::parse('9001015800088');

        $this->assertNotNull($parsed);
        $this->assertSame('1990-01-01', $parsed->dateOfBirth->format('Y-m-d'));
        $this->assertFalse($parsed->permanentResident);
    }

    public function test_formatting_is_ignored(): void
    {
        $this->assertTrue(SouthAfricanId::isValid('900101 5800 088'));
        $this->assertTrue(SouthAfricanId::isValid('900101-5800-088'));
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects(string $number, string $why): void
    {
        $this->assertFalse(SouthAfricanId::isValid($number), $why);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'wrong check digit' => ['9001015800086', 'a single mistyped digit must not pass'],
            'impossible date' => ['9002305800085', '30 February is not a date'],
            'too short' => ['12345', 'an SA ID is thirteen digits'],
            'too long' => ['90010158000880', 'an SA ID is thirteen digits'],
            'not numeric' => ['abcdefghijklm', 'letters are not an SA ID'],
            'empty' => ['', 'nothing is not an ID'],
        ];
    }

    public function test_permanent_residents_are_recognised(): void
    {
        // Same identity, citizenship digit flipped to 1; check digit recomputed.
        $resident = null;

        for ($d = 0; $d <= 9; $d++) {
            $candidate = '900101580018'.$d;

            if (SouthAfricanId::isValid($candidate)) {
                $resident = SouthAfricanId::parse($candidate);
                break;
            }
        }

        $this->assertNotNull($resident, 'a permanent-resident number should exist for this identity');
        $this->assertTrue($resident->permanentResident);
    }

    public function test_the_mask_shows_enough_to_recognise_and_not_enough_to_use(): void
    {
        $masked = Normalise::maskId('9001015800088');

        $this->assertSame('9001••••••088', $masked);
        $this->assertSame(13, mb_strlen($masked), 'the mask keeps the shape of the original');
    }

    public function test_phone_normalisation_collapses_local_and_international_forms(): void
    {
        $this->assertSame('+27821234567', Normalise::phone('082 123 4567'));
        $this->assertSame('+27821234567', Normalise::phone('+27 82 123 4567'));
        $this->assertSame('+27821234567', Normalise::phone('27821234567'));
        $this->assertNull(Normalise::phone('   '));
    }

    public function test_email_normalisation_is_case_and_space_insensitive(): void
    {
        $this->assertSame('nomsa@example.co.za', Normalise::email('  Nomsa@Example.CO.ZA '));
        $this->assertNull(Normalise::email(null));
    }
}
