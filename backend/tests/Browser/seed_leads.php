<?php
require __DIR__.'/../../vendor/autoload.php';
$a = require __DIR__.'/../../bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\{TouchChannel, TouchOutcome, UserRole};
use App\Models\{Application, User};
use App\Services\Leads\{MetaLeadImporter, TouchLog};

$admin = User::firstOrCreate(['email' => 'uat.admin@example.co.za'], [
    'name' => 'Thabiso Naleli', 'password' => bcrypt('uat-password-123'), 'role' => UserRole::ADMIN,
]);

// A slice of the real Leads Center list, in Meta's export shape.
$rows = [
    ['l_5001', '2026-08-29T19:44:00+0200', 'Mpho Moleko', 'mpho.moleko@example.co.za', '+27825550101'],
    ['l_5002', '2026-08-29T20:12:00+0200', 'Thulas Mbokane', 'thulas@example.co.za', '+27825550102'],
    ['l_5003', '2026-08-30T07:03:00+0200', 'Nonhlanhla Warona Mashego', 'warona@example.co.za', '+27825550103'],
    ['l_5004', '2026-08-30T09:31:00+0200', 'Refiloe Fana', 'refiloe@example.co.za', '+27825550104'],
    ['l_5005', '2026-08-30T14:08:00+0200', 'Fisokuhle Mbatha', 'fisokuhle@example.co.za', '+27825550105'],
    ['l_5006', '2026-08-31T11:22:00+0200', 'Katleho Xaba', 'katleho@example.co.za', '+27825550106'],
    ['l_5007', '2026-08-31T18:40:00+0200', 'Malehloa Machedi', 'malehloa@example.co.za', '+27825550107'],
    ['l_5008', '2026-09-01T06:15:00+0200', 'Rebecca Ncongwane', 'rebecca@example.co.za', '+27825550108'],
];

$csv = "id,created_time,ad_name,full_name,email,phone_number\n";
foreach ($rows as [$id, $when, $name, $email, $phone]) {
    $csv .= "{$id},{$when},\"KCS Aug 2026 — Digital Foundation\",{$name},{$email},p:{$phone}\n";
}

$handle = fopen('php://memory', 'r+');
fwrite($handle, $csv);
rewind($handle);
$result = app(MetaLeadImporter::class)->importStream($handle);
fclose($handle);

// Make the queue look like a real morning: some overdue, some called already.
$all = Application::where('source', 'meta_lead')->orderBy('id')->get();
$log = app(TouchLog::class);

$log->record($all[1], TouchChannel::PHONE, TouchOutcome::NO_ANSWER, 'Rang out twice.', $admin);
$log->record($all[2], TouchChannel::WHATSAPP, TouchOutcome::SENT_INFO, 'Sent fees, asked about February.', $admin);
$log->record($all[3], TouchChannel::PHONE, TouchOutcome::WILL_REGISTER, 'Paying on payday, 25th.', $admin);
$log->record($all[5], TouchChannel::PHONE, TouchOutcome::NOT_NOW, 'Next intake — starting a job.', $admin);

$all[0]->update(['next_action_at' => now()->subDays(4)]);
$all[4]->update(['next_action_at' => now()->subDay()]);
$all[7]->update(['next_action_at' => now()]);

echo json_encode(['imported' => $result['imported'], 'admin' => $admin->email], JSON_UNESCAPED_SLASHES), PHP_EOL;
