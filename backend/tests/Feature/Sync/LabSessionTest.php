<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Enums\EntitlementState;
use App\Models\Entitlement;
use App\Models\Learner;
use App\Models\Programme;
use App\Services\Identity\LabPin;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Signing in and out at a lab computer.
 *
 * The rotation this has to survive: three classes a day on the same machine,
 * every day. So what is asserted is mostly what the login REFUSES — a shared
 * seat where the last learner is still signed in, or a reference that can be
 * counted upwards to find out who is enrolled.
 */
class LabSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        RateLimiter::clear('lab-login:ip:127.0.0.1');
    }

    public function test_a_learner_signs_in_with_their_number_and_pin(): void
    {
        [$learner, $pin] = $this->learnerWithPin();

        $response = $this->postJson('/api/v1/sessions', [
            'learner_ref' => $learner->learner_ref,
            'pin' => $pin,
        ]);

        $response->assertCreated()
            ->assertJsonPath('learner.learner_ref', $learner->learner_ref)
            ->assertJsonStructure(['token', 'expires_at', 'learner', 'entitlements']);

        // The session is a working credential straight away.
        $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('learner.learner_ref', $learner->learner_ref);
    }

    public function test_a_lower_case_reference_typed_in_a_hurry_still_works(): void
    {
        [$learner, $pin] = $this->learnerWithPin();

        $this->postJson('/api/v1/sessions', [
            'learner_ref' => strtolower($learner->learner_ref),
            'pin' => $pin,
        ])->assertCreated();
    }

    public function test_a_wrong_pin_and_an_unknown_student_number_look_identical(): void
    {
        [$learner, $pin] = $this->learnerWithPin();

        $wrongPin = $this->postJson('/api/v1/sessions', [
            'learner_ref' => $learner->learner_ref,
            'pin' => '000000',
        ])->assertStatus(422);

        $noSuchLearner = $this->postJson('/api/v1/sessions', [
            'learner_ref' => 'NAL-2026-09999',
            'pin' => $pin,
        ])->assertStatus(422);

        // References run in sequence. If these two answers differed, anybody
        // could count upwards and learn who is on the roll.
        $this->assertSame(
            $wrongPin->json('errors.pin.0'),
            $noSuchLearner->json('errors.pin.0'),
        );
    }

    public function test_a_learner_who_has_never_been_given_a_pin_cannot_sign_in(): void
    {
        $learner = $this->learner();

        $this->postJson('/api/v1/sessions', [
            'learner_ref' => $learner->learner_ref,
            'pin' => '123456',
        ])->assertStatus(422);
    }

    public function test_guessing_is_throttled_per_student_without_locking_out_the_room(): void
    {
        [$mine, $myPin] = $this->learnerWithPin();
        [$classmate, $theirPin] = $this->learnerWithPin('Sipho', 'Dube', 'sipho@example.co.za');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/sessions', [
                'learner_ref' => $mine->learner_ref,
                'pin' => str_pad((string) $attempt, 6, '0', STR_PAD_LEFT),
            ])->assertStatus(422);
        }

        // The sixth guess at that number is refused even if it is right.
        $this->postJson('/api/v1/sessions', [
            'learner_ref' => $mine->learner_ref,
            'pin' => $myPin,
        ])->assertStatus(429);

        // But the whole lab shares one address, so the learner at the next
        // desk must still be able to start their class.
        $this->postJson('/api/v1/sessions', [
            'learner_ref' => $classmate->learner_ref,
            'pin' => $theirPin,
        ])->assertCreated();
    }

    public function test_a_correct_pin_clears_the_count_of_failed_guesses(): void
    {
        [$learner, $pin] = $this->learnerWithPin();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/v1/sessions', ['learner_ref' => $learner->learner_ref, 'pin' => '111111']);
        }

        $this->postJson('/api/v1/sessions', ['learner_ref' => $learner->learner_ref, 'pin' => $pin])
            ->assertCreated();

        // Four fumbles in the morning must not cost them the afternoon class.
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/v1/sessions', ['learner_ref' => $learner->learner_ref, 'pin' => '111111'])
                ->assertStatus(422);
        }
    }

    public function test_logging_out_ends_that_seat_and_nothing_else(): void
    {
        [$learner, $pin] = $this->learnerWithPin();

        // Her phone, activated once and kept.
        $phone = $learner->createToken('Learner phone', ['learner'])->plainTextToken;

        $lab = $this->postJson('/api/v1/sessions', [
            'learner_ref' => $learner->learner_ref, 'pin' => $pin,
        ])->json('token');

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$lab}")
            ->deleteJson('/api/v1/sessions')->assertNoContent();

        // The seat is cold for the next learner.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$lab}")->getJson('/api/v1/me')->assertUnauthorized();

        // Her own phone is untouched. Leaving a lab is not signing out of life.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$phone}")->getJson('/api/v1/me')->assertOk();
    }

    public function test_a_new_pin_stops_the_old_one_immediately(): void
    {
        [$learner, $pin] = $this->learnerWithPin();

        $replacement = app(LabPin::class)->issue($learner->fresh());

        $this->postJson('/api/v1/sessions', ['learner_ref' => $learner->learner_ref, 'pin' => $pin])
            ->assertStatus(422);

        RateLimiter::clear('lab-login:ref:'.$learner->learner_ref);

        $this->postJson('/api/v1/sessions', ['learner_ref' => $learner->learner_ref, 'pin' => $replacement])
            ->assertCreated();
    }

    public function test_the_pin_is_never_stored_or_returned_in_the_clear(): void
    {
        [$learner, $pin] = $this->learnerWithPin();

        $body = $this->postJson('/api/v1/sessions', [
            'learner_ref' => $learner->learner_ref, 'pin' => $pin,
        ])->assertCreated()->getContent();

        $this->assertStringNotContainsString($pin, (string) $body);
        $this->assertNotSame($pin, $learner->fresh()->pin_hash);
        $this->assertStringNotContainsString($pin, (string) $learner->fresh()->pin_hash);
    }

    // ------------------------------------------------------------- content

    public function test_the_content_pack_is_served_and_not_re_downloaded(): void
    {
        $first = $this->getJson('/api/v1/content/digital-foundation');

        $first->assertOk()
            ->assertJsonPath('content_code', 'digital-foundation')
            ->assertJsonPath('programme_name', 'Digital Foundation')
            ->assertJsonStructure(['workstreams', 'stages', 'total_days']);

        $this->assertNotEmpty($first->json('workstreams'));

        // A lab PC that already has the pack should spend nothing on it.
        $this->withHeader('If-None-Match', $first->headers->get('ETag'))
            ->getJson('/api/v1/content/digital-foundation')
            ->assertStatus(304);
    }

    public function test_content_needs_no_login_but_cannot_be_used_to_read_the_disk(): void
    {
        $this->getJson('/api/v1/content/digital-foundation')->assertOk();

        foreach (['../../.env', 'Digital-Foundation', 'no-such-pack'] as $code) {
            $this->getJson('/api/v1/content/'.$code)->assertNotFound();
        }
    }

    // ------------------------------------------------------------- helpers

    /** @return array{0: Learner, 1: string} */
    private function learnerWithPin(
        string $first = 'Thabiso',
        string $last = 'Mokoena',
        string $email = 'thabiso@example.co.za',
    ): array {
        $learner = $this->learner($first, $last, $email);

        Entitlement::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', 'PPO')->value('id'),
            'state' => EntitlementState::ACTIVE,
            'unlocked_at' => now(),
        ]);

        return [$learner, app(LabPin::class)->issue($learner)];
    }

    private function learner(
        string $first = 'Thabiso',
        string $last = 'Mokoena',
        string $email = 'thabiso@example.co.za',
    ): Learner {
        return Learner::create([
            'learner_ref' => 'NAL-2026-'.str_pad((string) (Learner::count() + 1), 5, '0', STR_PAD_LEFT),
            'first_registered_year' => 2026,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
        ]);
    }
}
