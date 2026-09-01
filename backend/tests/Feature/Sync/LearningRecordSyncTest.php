<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Enums\CompetenceResult;
use App\Enums\EntitlementState;
use App\Models\Entitlement;
use App\Models\Learner;
use App\Models\Programme;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The learning record, across devices.
 *
 * The property being defended is one sentence: the learner's account owns the
 * work, and a device is only ever a working copy. Everything asserted here is
 * a way that could go wrong — one lab PC shared by thirty learners, a phone
 * that was offline all morning, a retry that arrives twice, a clock nobody has
 * set since 2019, and a client that would quite like to mark itself competent.
 */
class LearningRecordSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        Storage::fake('local');
    }

    // ------------------------------------------------------------- the basics

    public function test_the_record_routes_refuse_an_anonymous_caller(): void
    {
        $this->getJson('/api/v1/me/progress')->assertUnauthorized();
        $this->postJson('/api/v1/me/progress', [])->assertUnauthorized();
        $this->postJson('/api/v1/me/evidence', [])->assertUnauthorized();
        $this->getJson('/api/v1/me/evidence/anything')->assertUnauthorized();
    }

    public function test_a_push_returns_the_whole_record_so_a_client_never_merges_twice(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $response = $this->push($token, [
            'device' => 'Learner phone',
            'sub_steps' => [
                $this->subStep('day-01-task-1-step-1', 'day-01-task-1', true, '2026-09-01T08:00:00+02:00'),
                $this->subStep('day-01-task-1-step-2', 'day-01-task-1', false),
            ],
            'submissions' => [
                ['task_id' => 'day-01-task-1', 'submitted_at' => '2026-09-01T08:30:00+02:00', 'confidence_rating' => 4],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('programme.code', 'PPO')
            // Every programme names the pack it teaches from, whether or not
            // anyone has written it yet. Naming it is what lets the client say
            // "your course is not loaded" instead of guessing at a pack — see
            // ContentPackTest for the pack that is actually installed.
            ->assertJsonPath('programme.content_code', 'people-payroll-operations')
            ->assertJsonPath('programme.entitlement_state', 'active')
            ->assertJsonCount(2, 'sub_steps')
            ->assertJsonCount(1, 'submissions')
            ->assertJsonPath('sub_steps.0.complete', true)
            ->assertJsonPath('sub_steps.1.complete', false)
            ->assertJsonPath('submissions.0.confidence_rating', 4)
            // Handed in, not yet judged. Those are different facts and the
            // record keeps them apart.
            ->assertJsonPath('submissions.0.result', 'not_yet_assessed');

        $this->assertNotNull($response->json('server_time'));

        // And a plain read gives back exactly what the push said it would.
        $this->get_('/api/v1/me/progress', $token)
            ->assertOk()
            ->assertJsonCount(2, 'sub_steps')
            ->assertJsonPath('sub_steps.0.sub_step_id', 'day-01-task-1-step-1');
    }

    // ------------------------------------------------- one PC, many learners

    public function test_three_learners_sharing_one_lab_pc_never_see_each_other(): void
    {
        [$a, $tokenA] = $this->enrolledLearner('Naledi', 'Khumalo', 'naledi@example.co.za');
        [$b, $tokenB] = $this->enrolledLearner('Sipho', 'Dube', 'sipho@example.co.za');
        [$c, $tokenC] = $this->enrolledLearner('Lerato', 'Nkosi', 'lerato@example.co.za');

        // Morning, afternoon, evening — same machine, one after the other.
        $this->push($tokenA, ['device' => 'KCS Lab PC 4', 'sub_steps' => [
            $this->subStep('step-a1', 'task-a', true),
            $this->subStep('step-a2', 'task-a', true),
        ]])->assertOk();

        $this->push($tokenB, ['device' => 'KCS Lab PC 4', 'sub_steps' => [
            $this->subStep('step-b1', 'task-b', true),
        ]])->assertOk();

        $this->push($tokenC, ['device' => 'KCS Lab PC 4', 'sub_steps' => [
            $this->subStep('step-c1', 'task-c', true),
            $this->subStep('step-c2', 'task-c', true),
            $this->subStep('step-c3', 'task-c', true),
        ]])->assertOk();

        // Each learner logs back in and finds their own work and only theirs.
        $this->assertSubStepIds(['step-a1', 'step-a2'], $tokenA);
        $this->assertSubStepIds(['step-b1'], $tokenB);
        $this->assertSubStepIds(['step-c1', 'step-c2', 'step-c3'], $tokenC);

        $this->assertSame(2, $a->subSteps()->count());
        $this->assertSame(1, $b->subSteps()->count());
        $this->assertSame(3, $c->subSteps()->count());
    }

    // ----------------------------------------------------- phone ⇄ lab PC

    public function test_work_done_on_a_phone_is_waiting_on_the_lab_pc_and_back_again(): void
    {
        [, $token] = $this->enrolledLearner();

        // At home, on the phone.
        $this->push($token, ['device' => 'Learner phone', 'sub_steps' => [
            $this->subStep('step-1', 'task-1', true),
            $this->subStep('step-2', 'task-1', true),
        ]])->assertOk();

        // Next morning at KCS, on whichever machine was free. Same account,
        // different device, no export, no flash drive.
        $onThePc = $this->get_('/api/v1/me/progress', $token)->assertOk();
        $this->assertSame(['step-1', 'step-2'], collect($onThePc->json('sub_steps'))->pluck('sub_step_id')->all());

        // She carries on there.
        $this->push($token, ['device' => 'KCS Lab PC 17', 'sub_steps' => [
            $this->subStep('step-3', 'task-1', true),
        ]])->assertOk();

        // And that evening the phone catches up.
        $this->assertSubStepIds(['step-1', 'step-2', 'step-3'], $token);
    }

    public function test_two_devices_that_were_both_offline_are_merged_not_overwritten(): void
    {
        [, $token] = $this->enrolledLearner();

        // The phone did steps 1 and 2 on the taxi with no signal.
        // The lab PC did step 3 that afternoon, also offline.
        // Both push later. Neither can see the other, and both are right.
        $this->push($token, ['device' => 'Learner phone', 'sub_steps' => [
            $this->subStep('step-1', 'task-1', true, '2026-09-01T07:10:00+02:00'),
            $this->subStep('step-2', 'task-1', true, '2026-09-01T07:25:00+02:00'),
        ]])->assertOk();

        $merged = $this->push($token, ['device' => 'KCS Lab PC 17', 'sub_steps' => [
            $this->subStep('step-3', 'task-1', true, '2026-09-01T14:40:00+02:00'),
        ]])->assertOk();

        // Three, not one. A morning is not thrown away by an afternoon.
        $this->assertSame(
            ['step-1', 'step-2', 'step-3'],
            collect($merged->json('sub_steps'))->pluck('sub_step_id')->all(),
        );
        $this->assertTrue(collect($merged->json('sub_steps'))->every(fn (array $s): bool => $s['complete'] === true));
    }

    // ------------------------------------------------------- the merge rules

    public function test_a_device_with_a_wrong_clock_cannot_erase_completed_work(): void
    {
        [, $token] = $this->enrolledLearner();

        $this->push($token, ['device' => 'Learner phone', 'sub_steps' => [
            $this->subStep('step-1', 'task-1', true, '2026-09-01T08:00:00+02:00'),
        ]])->assertOk();

        // A lab PC whose clock has never been set pushes a stale, incomplete
        // copy of the same step. Half the machines in the lab are like this;
        // none of them may un-do an afternoon of work.
        $stale = $this->push($token, ['device' => 'KCS Lab PC 9', 'sub_steps' => [
            [
                'sub_step_id' => 'step-1',
                'task_id' => 'task-1',
                'complete' => false,
                'client_updated_at' => '2019-04-02T11:00:00+02:00',
            ],
        ]])->assertOk();

        $this->assertTrue($stale->json('sub_steps.0.complete'), 'completion is a ratchet');
    }

    public function test_the_earliest_completion_time_is_the_one_kept(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $this->push($token, ['sub_steps' => [
            $this->subStep('step-1', 'task-1', true, '2026-09-01T14:00:00+02:00'),
        ]])->assertOk();

        $this->push($token, ['sub_steps' => [
            $this->subStep('step-1', 'task-1', true, '2026-09-01T08:00:00+02:00'),
        ]])->assertOk();

        $step = $learner->subSteps()->where('sub_step_id', 'step-1')->firstOrFail();

        // She did it at eight. The second device just heard about it later.
        $this->assertSame('2026-09-01 06:00:00', $step->completed_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_pushing_the_same_batch_twice_changes_nothing(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $batch = [
            'device' => 'Learner phone',
            'sub_steps' => [
                $this->subStep('step-1', 'task-1', true, '2026-09-01T08:00:00+02:00'),
                $this->subStep('step-2', 'task-1', true, '2026-09-01T08:05:00+02:00'),
            ],
            'submissions' => [
                ['task_id' => 'task-1', 'submitted_at' => '2026-09-01T08:10:00+02:00', 'confidence_rating' => 3],
            ],
        ];

        // A client that lost the response to its first push simply sends it
        // again. That has to be free, or nobody can sync over a bad line.
        $this->push($token, $batch)->assertOk();
        $this->push($token, $batch)->assertOk();
        $this->push($token, $batch)->assertOk();

        $this->assertSame(2, $learner->subSteps()->count());
        $this->assertSame(1, $learner->submissions()->count());
    }

    public function test_a_batch_that_says_nothing_about_a_rating_does_not_erase_it(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $this->push($token, ['submissions' => [
            ['task_id' => 'task-1', 'submitted_at' => '2026-09-01T08:00:00+02:00', 'confidence_rating' => 5],
        ]])->assertOk();

        $this->push($token, ['submissions' => [
            ['task_id' => 'task-1', 'submitted_at' => '2026-09-02T08:00:00+02:00'],
        ]])->assertOk();

        $submission = $learner->submissions()->where('task_id', 'task-1')->firstOrFail();

        $this->assertSame(5, $submission->confidence_rating, 'silence is not an answer of null');
        // A resubmission is newer and should show as the latest hand-in.
        $this->assertSame('2026-09-02 06:00:00', $submission->submitted_at->utc()->format('Y-m-d H:i:s'));
    }

    // --------------------------------------------- the line that never moves

    public function test_a_client_cannot_mark_itself_competent(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $this->push($token, ['submissions' => [
            [
                'task_id' => 'task-1',
                'submitted_at' => '2026-09-01T08:00:00+02:00',
                'confidence_rating' => 5,
                // A rooted phone, or simply an app that got clever.
                'result' => 'competent',
                'assessed_at' => '2026-09-01T08:00:01+02:00',
                'feedback' => 'Excellent work.',
            ],
        ]])->assertOk()->assertJsonPath('submissions.0.result', 'not_yet_assessed');

        $submission = $learner->submissions()->where('task_id', 'task-1')->firstOrFail();

        $this->assertSame(CompetenceResult::NOT_YET_ASSESSED, $submission->result);
        $this->assertNull($submission->assessed_at);
        $this->assertNull($submission->feedback);
    }

    public function test_an_assessors_verdict_survives_the_next_sync_from_the_learners_phone(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $this->push($token, ['submissions' => [
            ['task_id' => 'task-1', 'submitted_at' => '2026-09-01T08:00:00+02:00'],
        ]])->assertOk();

        // The school assesses it — the only path there is, and not one the
        // API exposes.
        $learner->submissions()->where('task_id', 'task-1')->update([
            'result' => CompetenceResult::COMPETENT->value,
            'assessed_at' => now(),
            'feedback' => 'Clear evidence of the whole process.',
        ]);

        // The phone, which knows nothing about that, syncs again.
        $after = $this->push($token, ['submissions' => [
            ['task_id' => 'task-1', 'submitted_at' => '2026-09-01T08:00:00+02:00', 'confidence_rating' => 2],
        ]])->assertOk();

        $after->assertJsonPath('submissions.0.result', 'competent')
            ->assertJsonPath('submissions.0.feedback', 'Clear evidence of the whole process.')
            ->assertJsonPath('submissions.0.confidence_rating', 2);
    }

    // -------------------------------------------------------------- evidence

    public function test_evidence_uploads_once_however_many_times_it_is_sent(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $payload = [
            'client_evidence_id' => 'b6d1f0a2-0e2f-4f0e-9a1b-2c3d4e5f6071',
            'task_id' => 'day-01-task-1',
            'description' => 'My keyboard practice sheet',
            'device' => 'Learner phone',
        ];

        $first = $this->postFile($token, $payload, UploadedFile::fake()->image('practice.jpg'));
        $first->assertCreated()->assertJsonPath('evidence.file_name', 'practice.jpg');

        // The phone never saw that response — one bar of signal on a taxi —
        // so it sends the whole 4 MB again.
        $second = $this->postFile($token, $payload, UploadedFile::fake()->image('practice.jpg'));
        $second->assertOk();

        $this->assertSame(1, $learner->evidence()->count(), 'one photo, not two');
        $this->assertSame(
            $first->json('evidence.client_evidence_id'),
            $second->json('evidence.client_evidence_id'),
        );

        $stored = $learner->evidence()->firstOrFail();
        Storage::disk('local')->assertExists($stored->storage_path);
        $this->assertNotNull($stored->checksum);
        $this->assertGreaterThan(0, $stored->byte_size);
    }

    public function test_a_written_answer_is_evidence_like_any_other(): void
    {
        [, $token] = $this->enrolledLearner();

        // The app stores typed work as a text file rather than growing a
        // second evidence path; the backend follows the same rule.
        $this->postFile($token, [
            'client_evidence_id' => 'written-1',
            'task_id' => 'day-02-task-3',
        ], UploadedFile::fake()->createWithContent(
            'written-answer.txt',
            'Input is what goes in, processing is what the computer does with it.',
        ))->assertCreated()->assertJsonPath('evidence.mime_type', 'text/plain');
    }

    public function test_evidence_shows_up_in_the_record_and_can_be_fetched_back(): void
    {
        [, $token] = $this->enrolledLearner();

        $this->postFile($token, [
            'client_evidence_id' => 'ev-1',
            'task_id' => 'task-1',
        ], UploadedFile::fake()->image('proof.png'))->assertCreated();

        $record = $this->get_('/api/v1/me/progress', $token)->assertOk();
        $record->assertJsonCount(1, 'evidence')
            ->assertJsonPath('evidence.0.client_evidence_id', 'ev-1');

        // A learner who worked at home and then sat down at a lab PC pulls
        // her own file back the same way she sent it.
        $this->get_('/api/v1/me/evidence/ev-1', $token)->assertOk();
    }

    public function test_one_learner_cannot_download_another_learners_evidence(): void
    {
        [, $mine] = $this->enrolledLearner();
        [, $theirs] = $this->enrolledLearner('Sipho', 'Dube', 'sipho@example.co.za');

        $this->postFile($mine, [
            'client_evidence_id' => 'private-1',
            'task_id' => 'task-1',
        ], UploadedFile::fake()->image('id-document.jpg'))->assertCreated();

        // Not 403 — 404. A permission message would confirm the file is real.
        $this->get_('/api/v1/me/evidence/private-1', $theirs)->assertNotFound();
    }

    public function test_an_oversized_or_unwelcome_file_is_refused(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $this->postFile($token, [
            'client_evidence_id' => 'too-big',
            'task_id' => 'task-1',
        ], UploadedFile::fake()->create('holiday.mp4', 40 * 1024, 'video/mp4'))
            ->assertStatus(422);

        $this->assertSame(0, $learner->evidence()->count());
    }

    public function test_a_task_id_that_tries_to_climb_out_of_the_evidence_folder_cannot(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $this->postFile($token, [
            'client_evidence_id' => 'traversal-1',
            'task_id' => '../../../../.env',
        ], UploadedFile::fake()->image('innocent.jpg'))->assertCreated();

        $path = $learner->evidence()->firstOrFail()->storage_path;

        $this->assertStringStartsWith('evidence/'.$learner->learner_ref.'/', $path);
        $this->assertStringNotContainsString('..', $path);
        Storage::disk('local')->assertExists($path);
    }

    // ----------------------------------------------------------- who may sync

    public function test_a_learner_with_nothing_open_has_nothing_to_sync(): void
    {
        $learner = $this->learner();
        $token = $this->deviceTokenFor($learner);

        // Registered, never paid: no entitlement was ever unlocked.
        $this->get_('/api/v1/me/progress', $token)->assertStatus(422);
    }

    public function test_a_locked_programme_is_refused_by_name(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        Entitlement::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', 'BIA')->value('id'),
            'state' => EntitlementState::LOCKED,
        ]);

        $this->get_('/api/v1/me/progress?programme=BIA', $token)->assertForbidden();
    }

    public function test_an_expired_programme_can_still_hand_in_work_already_done(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        $learner->entitlements()->update([
            'state' => EntitlementState::EXPIRED->value,
            'expires_at' => now()->subDay(),
        ]);

        // Her access ran out on Friday; the work she did on Thursday, on a
        // phone that had no signal until Monday, is still hers.
        $this->push($token, ['sub_steps' => [
            $this->subStep('step-1', 'task-1', true, '2026-09-01T08:00:00+02:00'),
        ]])->assertOk()->assertJsonPath('programme.entitlement_state', 'expired');

        $this->assertSame(1, $learner->subSteps()->count());
    }

    public function test_two_open_programmes_must_be_told_apart(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        Entitlement::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', 'BIA')->value('id'),
            'state' => EntitlementState::ACTIVE,
            'unlocked_at' => now(),
        ]);

        // Guessing which one she meant would file the work under the wrong
        // programme, silently.
        $this->get_('/api/v1/me/progress', $token)->assertStatus(422);
        $this->get_('/api/v1/me/progress?programme=PPO', $token)->assertOk()
            ->assertJsonPath('programme.code', 'PPO');
    }

    public function test_work_is_filed_under_the_programme_it_belongs_to(): void
    {
        [$learner, $token] = $this->enrolledLearner();

        Entitlement::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', 'BIA')->value('id'),
            'state' => EntitlementState::ACTIVE,
            'unlocked_at' => now(),
        ]);

        // Two content packs can name a step the same thing. They are still
        // two different steps.
        $this->push($token, ['programme' => 'PPO', 'sub_steps' => [
            $this->subStep('day-01-task-1-step-1', 'day-01-task-1', true),
        ]])->assertOk();

        $bia = $this->push($token, ['programme' => 'BIA', 'sub_steps' => [
            $this->subStep('day-01-task-1-step-1', 'day-01-task-1', false),
        ]])->assertOk();

        $this->assertSame(2, $learner->subSteps()->count());
        $this->assertFalse($bia->json('sub_steps.0.complete'));
        $this->assertTrue(
            $this->get_('/api/v1/me/progress?programme=PPO', $token)->json('sub_steps.0.complete'),
        );
    }

    public function test_a_batch_beyond_the_cap_is_refused_so_the_client_chunks_it(): void
    {
        [, $token] = $this->enrolledLearner();
        config(['sync.max_sub_steps' => 3]);

        $this->push($token, ['sub_steps' => [
            $this->subStep('s1', 't', true),
            $this->subStep('s2', 't', true),
            $this->subStep('s3', 't', true),
            $this->subStep('s4', 't', true),
        ]])->assertStatus(422);
    }

    // ------------------------------------------------------------- helpers

    /** @return array{0: Learner, 1: string} */
    private function enrolledLearner(
        string $first = 'Thabiso',
        string $last = 'Mokoena',
        string $email = 'thabiso@example.co.za',
        string $programme = 'PPO',
    ): array {
        $learner = $this->learner($first, $last, $email);

        Entitlement::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', $programme)->value('id'),
            'state' => EntitlementState::ACTIVE,
            'unlocked_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);

        return [$learner, $this->deviceTokenFor($learner)];
    }

    private function learner(string $first = 'Thabiso', string $last = 'Mokoena', string $email = 'thabiso@example.co.za'): Learner
    {
        return Learner::create([
            'learner_ref' => 'NAL-2026-'.str_pad((string) (Learner::count() + 1), 5, '0', STR_PAD_LEFT),
            'first_registered_year' => 2026,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
        ]);
    }

    private function deviceTokenFor(Learner $learner): string
    {
        return $learner->createToken('Test device', ['learner'])->plainTextToken;
    }

    /**
     * Every request goes through here so the guard is cleared first.
     *
     * A test process keeps one container across many requests, and Sanctum's
     * RequestGuard caches the learner it resolved — so without this, the
     * second token in a test silently returns the first token's learner and
     * an isolation test passes when it should fail. Production never sees
     * this (one php-fpm process per request, no Octane), but a test that
     * cannot tell two learners apart is worse than no test.
     */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /** @param  array<string, mixed>  $batch */
    private function push(string $token, array $batch): TestResponse
    {
        return $this->as($token)->postJson('/api/v1/me/progress', $batch);
    }

    private function get_(string $uri, string $token): TestResponse
    {
        return $this->as($token)->getJson($uri);
    }

    /** @param  array<string, mixed>  $meta */
    private function postFile(string $token, array $meta, UploadedFile $file): TestResponse
    {
        return $this->as($token)
            ->post('/api/v1/me/evidence', [...$meta, 'file' => $file], ['Accept' => 'application/json']);
    }

    /** @return array<string, mixed> */
    private function subStep(string $id, string $taskId, bool $complete, ?string $completedAt = null): array
    {
        return [
            'sub_step_id' => $id,
            'task_id' => $taskId,
            'complete' => $complete,
            'completed_at' => $complete ? ($completedAt ?? '2026-09-01T10:00:00+02:00') : null,
            'client_updated_at' => $completedAt ?? '2026-09-01T10:00:00+02:00',
        ];
    }

    /** @param  array<int, string>  $expected */
    private function assertSubStepIds(array $expected, string $token): void
    {
        $this->assertSame(
            $expected,
            collect($this->get_('/api/v1/me/progress', $token)->json('sub_steps'))->pluck('sub_step_id')->all(),
        );
    }
}
