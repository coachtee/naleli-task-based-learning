<?php

/*
 * Everything the server does before the learner touches anything, so
 * journey.mjs can start where a real person starts — at the link in the email.
 *
 *   php artisan serve --port=8123 &
 *   php tests/Browser/setup_journey.php > /tmp/journey.json
 *   JOURNEY="$(cat /tmp/journey.json)" SHOTS=/tmp/shots node tests/Browser/journey.mjs
 *
 * Re-running wipes the previous UAT prospect first, so it proves something
 * twice rather than tripping over yesterday's record.
 */

use App\Models\Learner;
use App\Models\Offering;
use App\Services\Enrolment\ApplicationAcceptor;
use App\Services\Enrolment\EnrolmentActivator;
use App\Services\Registration\LearnerLinks;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

require __DIR__.'/../../vendor/autoload.php';
$a = require __DIR__.'/../../bootstrap/app.php';
$a->make(Kernel::class)->bootstrap();

// Sign for the host the browser will actually ask for; a signature covers the
// whole URL, so signing against APP_URL and then browsing somewhere else fails.
URL::forceRootUrl(getenv('UAT_BASE') ?: 'http://127.0.0.1:8123');

// Wipe any previous run of this journey so it proves something twice.
foreach (Learner::where('email', 'uat.prospect@example.co.za')->get() as $old) {
    $old->subSteps()->delete();
    $old->submissions()->delete();
    $old->evidence()->delete();
    $old->applications()->delete();
    $old->enrolments()->delete();
    $old->invoices()->delete();
    $old->payments()->delete();
    $old->entitlements()->delete();
    $old->accessTokens()->delete();
    $old->tokens()->delete();
    $old->delete();
}

// 1 — the website form, exactly as Fluent Forms posts it.
$payload = [
    'source' => 'fluentform', 'form_id' => 8, 'submission_id' => random_int(100000, 999999),
    'submitted_at' => now()->toIso8601String(),
    'applicant' => [
        'first_name' => 'Palesa', 'last_name' => 'Mahlangu',
        'email' => 'uat.prospect@example.co.za', 'phone' => '082 555 0134',
        'id_type' => 'sa_id', 'id_number' => '9001015800088',
    ],
    'programme_code' => 'DOPF', 'enrolment_plan' => 'monthly',
];
$body = json_encode($payload, JSON_THROW_ON_ERROR);
$request = Request::create('/api/v1/intake/application', 'POST', server: [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
    'HTTP_X_KCS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, (string) config('webhooks.fluentform.secret')),
], content: $body);
$response = $a->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
$intake = json_decode($response->getContent(), true);

$learner = Learner::where('email', 'uat.prospect@example.co.za')->firstOrFail();
$application = $learner->applications()->latest('id')->firstOrFail();

// 2 — the registrar accepts, 3 — the learner pays.
$enrolment = app(ApplicationAcceptor::class)->accept($application, Offering::where('code', 'DOPF-2027')->firstOrFail());
$result = app(EnrolmentActivator::class)->confirmInvoiceManually(
    $enrolment->activatingInvoice(), 'payat_go', 'PAYAT-UAT-'.$application->id,
);

echo json_encode([
    'intake_status' => $intake['status'] ?? null,
    'learner_ref' => $learner->learner_ref,
    'enrolment_status' => $enrolment->fresh()->status->value,
    'app_token' => $result['plain_token'],
    // The same link the email carries, signed for this host so a browser can follow it.
    'access_link' => app(LearnerLinks::class)->workspaceAccess($learner->fresh()),
], JSON_UNESCAPED_SLASHES);
