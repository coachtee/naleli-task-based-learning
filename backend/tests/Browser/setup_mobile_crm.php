<?php

/*
 * Seeds one admin user and one of everything the mobile CRM screens show,
 * so the visual check has real data to render rather than empty states.
 *
 *   php artisan serve --port=8123 &
 *   php tests/Browser/setup_mobile_crm.php > /tmp/mobile_crm.json
 *   node tests/Browser/mobile_crm_check.mjs
 */

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Learner;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$a = require __DIR__.'/../../bootstrap/app.php';
$a->make(Kernel::class)->bootstrap();

$admin = User::updateOrCreate(
    ['email' => 'uat.staff@example.co.za'],
    ['name' => 'Bruce Masingue', 'password' => bcrypt('uat-password-123'), 'role' => UserRole::ADMIN],
);

foreach (Learner::whereIn('email', ['uat.lead@example.co.za', 'uat.learner@example.co.za'])->get() as $old) {
    $old->applications()->delete();
    $old->invoices()->delete();
    $old->enrolments()->delete();
    $old->delete();
}

$programme = Programme::where('code', 'DOPF')->firstOrFail();

$leadLearner = Learner::create([
    'learner_ref' => 'NAL-2026-'.str_pad((string) (Learner::count() + 1), 5, '0', STR_PAD_LEFT),
    'first_registered_year' => 2026,
    'first_name' => 'Mpho', 'last_name' => 'Moleko',
    'email' => 'uat.lead@example.co.za', 'phone' => '082 555 0101', 'whatsapp' => '082 555 0101',
]);
$lead = Application::create([
    'learner_id' => $leadLearner->id, 'programme_id' => $programme->id,
    'status' => ApplicationStatus::LEAD, 'source' => ApplicationSource::META_LEAD,
    'campaign' => 'KCS Aug 2026', 'applied_at' => now()->subWeek(), 'next_action_at' => now()->subDays(4),
]);

$learner = Learner::create([
    'learner_ref' => 'NAL-2026-'.str_pad((string) (Learner::count() + 1), 5, '0', STR_PAD_LEFT),
    'first_registered_year' => 2026,
    'first_name' => 'Palesa', 'last_name' => 'Mahlangu',
    'email' => 'uat.learner@example.co.za', 'phone' => '082 555 0134', 'whatsapp' => '082 555 0134',
    'status' => 'active',
]);
$enrolment = Enrolment::create([
    'learner_id' => $learner->id, 'programme_id' => $programme->id, 'status' => 'active',
]);
Invoice::create([
    'learner_id' => $learner->id, 'enrolment_id' => $enrolment->id, 'sequence' => 1,
    'description' => 'Registration fee', 'amount_cents' => 50000,
    'status' => InvoiceStatus::DUE, 'due_on' => now()->addWeek(),
]);

echo json_encode([
    'admin_email' => $admin->email,
    'admin_password' => 'uat-password-123',
    'lead_id' => $lead->id,
    'learner_id' => $learner->id,
], JSON_UNESCAPED_SLASHES);
