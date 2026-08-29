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

    public function test_a_three_month_block_is_one_invoice_not_three(): void
    {
        $offering = $this->offering([
            'billing_model' => BillingModel::FIXED_BLOCK,
            'price_cents' => 95000,
            'access_duration_days' => 90,
        ]);

        $lines = $this->fees->linesFor($offering);

        $this->assertCount(1, $lines, 'a block is one payment, not one per month');
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

    public function test_every_seeded_offering_starts_closed_for_business(): void
    {
        $open = Offering::where('status', OfferingStatus::OPEN)->count();

        $this->assertSame(
            0,
            $open,
            'no offering may be sellable until a person confirms its price and opens it',
        );
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
