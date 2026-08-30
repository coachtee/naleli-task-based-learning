<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\ActivationRule;
use App\Enums\BillingModel;
use App\Enums\OfferingStatus;
use App\Models\Offering;
use App\Models\Programme;
use App\Services\Billing\FeeSchedule;
use Database\Seeders\ProgrammeSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The correction, asserted.
 *
 * A fixed three-month block is ONE payment of R950 for ninety days of access.
 * An earlier end-to-end test billed it as R500 plus three instalments of R950
 * — R3,350 for something meant to cost R950 — because the invoice shape was
 * decided by the caller rather than derived from the product. These tests
 * exist so that cannot happen silently again.
 */
class FeeScheduleTest extends TestCase
{
    use RefreshDatabase;

    private FeeSchedule $fees;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        $this->fees = app(FeeSchedule::class);
    }

    /**
     * The confirmed KCS Career Module model, asserted against the seeded
     * offering rather than a fixture — so a seeder edit that changes what
     * learners are charged fails here rather than in production.
     */
    public function test_the_career_module_bills_r500_registration_then_r950_for_three_months(): void
    {
        $offering = Offering::where('code', 'PPO-2026')->firstOrFail();

        $this->assertSame(BillingModel::DEPOSIT_BALANCE, $offering->billing_model);
        $this->assertSame(335000, $offering->price_cents, 'R3,350 in total');

        $lines = $this->fees->linesFor($offering);
        $this->fees->assertConsistent($offering, $lines);

        $this->assertCount(4, $lines);

        $this->assertSame('Registration fee', $lines[0]->description);
        $this->assertSame(50000, $lines[0]->amountCents);
        $this->assertTrue($lines[0]->activatesEnrolment, 'registration opens access');
        $this->assertSame(0, $lines[0]->dueInDays);

        foreach ([1, 2, 3] as $month) {
            $this->assertSame(95000, $lines[$month]->amountCents, "month {$month} is R950");
            $this->assertFalse($lines[$month]->activatesEnrolment);
            $this->assertSame(30 * $month, $lines[$month]->dueInDays, 'one month apart');
        }

        $this->assertSame(335000, array_sum(array_map(fn ($l) => $l->amountCents, $lines)));
        $this->assertSame('R500 registration + R950 × 3 months', $offering->terms());
    }

    public function test_a_fixed_block_is_one_invoice(): void
    {
        $offering = $this->offering([
            'billing_model' => BillingModel::FIXED_BLOCK,
            'price_cents' => 95000,
            'access_duration_days' => 90,
        ]);

        $lines = $this->fees->linesFor($offering);

        $this->assertCount(1, $lines, 'a block is one payment for the whole period');
        $this->assertSame(95000, $lines[0]->amountCents);
        $this->assertTrue($lines[0]->activatesEnrolment);
        $this->assertStringContainsString('3 months access', $lines[0]->description);

        // The total charged is the price on the product. Nothing else.
        $this->fees->assertConsistent($offering, $lines);
        $this->assertSame(95000, array_sum(array_map(fn ($l) => $l->amountCents, $lines)));
    }

    public function test_a_one_time_purchase_is_one_invoice(): void
    {
        $offering = $this->offering([
            'billing_model' => BillingModel::ONE_TIME,
            'price_cents' => 75000,
            'access_duration_days' => null,
        ]);

        $lines = $this->fees->linesFor($offering);

        $this->assertCount(1, $lines);
        $this->assertSame(75000, $lines[0]->amountCents);
    }

    public function test_deposit_plus_balance_splits_without_inventing_money(): void
    {
        $offering = $this->offering([
            'billing_model' => BillingModel::DEPOSIT_BALANCE,
            'price_cents' => 335000,
            'deposit_cents' => 50000,
            'instalment_count' => 3,
            'activation_rule' => ActivationRule::ON_FIRST_PAYMENT,
        ]);

        $lines = $this->fees->linesFor($offering);
        $this->fees->assertConsistent($offering, $lines);

        $this->assertCount(4, $lines);
        $this->assertSame(50000, $lines[0]->amountCents);
        $this->assertTrue($lines[0]->activatesEnrolment, 'the deposit opens access');
        $this->assertSame(95000, $lines[1]->amountCents);

        // The instalments split the BALANCE. The total is still the price —
        // which is precisely what the earlier mistake got wrong.
        $this->assertSame(335000, array_sum(array_map(fn ($l) => $l->amountCents, $lines)));
    }

    public function test_an_uneven_balance_leaves_no_cent_behind(): void
    {
        $offering = $this->offering([
            'billing_model' => BillingModel::DEPOSIT_BALANCE,
            'price_cents' => 100000,
            'deposit_cents' => 10000,
            'instalment_count' => 7,   // 90000 / 7 does not divide
        ]);

        $lines = $this->fees->linesFor($offering);

        $this->assertSame(100000, array_sum(array_map(fn ($l) => $l->amountCents, $lines)));
        $this->fees->assertConsistent($offering, $lines);
    }

    public function test_when_access_waits_for_full_payment_the_last_instalment_activates(): void
    {
        $offering = $this->offering([
            'billing_model' => BillingModel::DEPOSIT_BALANCE,
            'price_cents' => 200000,
            'deposit_cents' => 50000,
            'instalment_count' => 2,
            'activation_rule' => ActivationRule::ON_FULL_PAYMENT,
        ]);

        $lines = $this->fees->linesFor($offering);

        $this->assertFalse($lines[0]->activatesEnrolment, 'the deposit alone does not open access');
        $this->assertTrue($lines[2]->activatesEnrolment, 'the final instalment does');
        $this->fees->assertConsistent($offering, $lines);
    }

    public function test_subscription_is_modelled_but_refuses_to_bill(): void
    {
        $offering = $this->offering([
            'billing_model' => BillingModel::SUBSCRIPTION,
            'price_cents' => 95000,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/modelled but not enabled/');

        $this->fees->linesFor($offering);
    }

    /**
     * "Fees on enquiry" is not a price. Anything without a confirmed one
     * stays shut, so nobody can be charged R0 by clicking through.
     */
    public function test_offerings_without_a_confirmed_price_stay_closed(): void
    {
        $unpriced = Offering::where('price_cents', 0)->get();

        $this->assertGreaterThan(0, $unpriced->count());

        foreach ($unpriced as $offering) {
            $this->assertSame(
                OfferingStatus::DRAFT,
                $offering->status,
                "{$offering->code} is priced at R0 and must not be open for sale",
            );
        }
    }

    /** @param array<string, mixed> $attributes */
    private function offering(array $attributes): Offering
    {
        return Offering::create(array_merge([
            'programme_id' => Programme::where('code', 'PPO')->value('id'),
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test offering',
            'activation_rule' => ActivationRule::ON_FIRST_PAYMENT,
            'status' => OfferingStatus::OPEN,
        ], $attributes));
    }
}
