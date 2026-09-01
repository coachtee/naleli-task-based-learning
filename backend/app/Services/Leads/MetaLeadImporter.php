<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Programme;
use App\Services\Identity\LearnerRegistry;
use App\Support\CatalogueManifest;
use App\Support\Normalise;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A Meta Leads Center export, turned into people on the registration ladder.
 *
 * Imported at LEAD, not as registrations. Somebody who tapped an instant form
 * asked what the course costs; they did not register, and filing them as
 * though they did means the pipeline can no longer tell an enquiry from a
 * student. Creating an application writes no invoice, so this costs nothing
 * but a row.
 *
 * Each lead becomes a real learner with a real reference. That does consume
 * references on people who may never enrol, which is the deliberate trade:
 * when one of them fills in the website form six weeks later, LearnerRegistry
 * matches them on email or phone and it is the SAME person, with the Facebook
 * lead still attached. Recognising a returning prospect is worth more than a
 * tidy numbering sequence.
 *
 * The header mapping is forgiving because Meta's column names change with the
 * form: a custom question becomes its own column, exports arrive with a BOM,
 * and phone numbers come out as "p:+27821234567".
 */
class MetaLeadImporter
{
    /** @var array<string, array<int, string>> */
    private const COLUMNS = [
        'lead_id' => ['id', 'lead_id', 'leadid'],
        'created' => ['created_time', 'created', 'createdtime', 'date_created'],
        'full_name' => ['full_name', 'fullname', 'name'],
        'first_name' => ['first_name', 'firstname'],
        'last_name' => ['last_name', 'lastname', 'surname'],
        'email' => ['email', 'email_address', 'emailaddress', 'work_email'],
        'phone' => ['phone_number', 'phone', 'phonenumber', 'mobile', 'cell'],
        'campaign' => ['ad_name', 'adname', 'campaign_name', 'campaignname', 'adset_name'],
        'form' => ['form_name', 'formname', 'form_id'],
        'platform' => ['platform'],
    ];

    public function __construct(private readonly LearnerRegistry $learners) {}

    /**
     * Which programme a lead is filed under when the export does not say.
     *
     * The Foundation, because that is where the catalogue says every learner
     * starts before specialising — a Facebook lead who has not spoken to
     * anybody yet has not chosen a specialisation either. An ad for a specific
     * block passes --programme and overrides this.
     */
    private function defaultProgrammeId(): ?int
    {
        return Programme::where('code', CatalogueManifest::FOUNDATION_CODE)->value('id');
    }

    /**
     * @return array{imported: int, duplicates: int, skipped: array<int, string>, campaign: ?string}
     */
    public function importFile(string $path, ?string $campaign = null, ?int $programmeId = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}.");
        }

        try {
            return $this->importStream($handle, $campaign, $programmeId);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array{imported: int, duplicates: int, skipped: array<int, string>, campaign: ?string}
     */
    public function importStream($handle, ?string $campaign = null, ?int $programmeId = null): array
    {
        $delimiter = $this->sniffDelimiter($handle);
        $header = fgetcsv($handle, 0, $delimiter);

        if ($header === false || $header === [null]) {
            throw new RuntimeException('That file has no header row.');
        }

        // Excel and Meta both like to prepend a byte-order mark, which
        // otherwise leaves the first column named "\u{FEFF}id", matching nothing.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? '';
        $map = $this->mapHeader($header);

        if (! isset($map['email']) && ! isset($map['phone'])) {
            throw new RuntimeException(
                'No email or phone column found. Columns seen: '.implode(', ', $header),
            );
        }

        $imported = 0;
        $duplicates = 0;
        $skipped = [];
        $row = 1;
        $detectedCampaign = null;

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row++;

            if ($line === [null] || $line === []) {
                continue;
            }

            $value = fn (string $key): ?string => isset($map[$key], $line[$map[$key]])
                ? $this->clean((string) $line[$map[$key]])
                : null;

            $email = Normalise::email($value('email'));
            $phone = Normalise::phone($this->stripMetaPrefix($value('phone')));

            if ($email === null && $phone === null) {
                $skipped[] = "Row {$row}: no email and no phone.";

                continue;
            }

            [$first, $last] = $this->splitName($value('full_name'), $value('first_name'), $value('last_name'));

            if ($first === '' && $last === '') {
                $skipped[] = "Row {$row}: no name.";

                continue;
            }

            $rowCampaign = $campaign ?? $value('campaign') ?? $value('form');
            $detectedCampaign ??= $rowCampaign;

            $result = $this->importOne(
                leadId: $value('lead_id') ?? ($email ?? $phone).'-'.$row,
                first: $first,
                last: $last,
                email: $email,
                phone: $phone,
                campaign: $rowCampaign,
                createdAt: $this->parseTime($value('created')),
                programmeId: $programmeId ?? $this->defaultProgrammeId(),
            );

            $result ? $imported++ : $duplicates++;
        }

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'campaign' => $campaign ?? $detectedCampaign,
        ];
    }

    /** @return bool true when a new lead was created */
    private function importOne(
        string $leadId,
        string $first,
        string $last,
        ?string $email,
        ?string $phone,
        ?string $campaign,
        ?Carbon $createdAt,
        ?int $programmeId,
    ): bool {
        return DB::transaction(function () use ($leadId, $first, $last, $email, $phone, $campaign, $createdAt, $programmeId): bool {
            // Meta's own lead id, so re-importing the same export is free.
            $existing = Application::where('source', ApplicationSource::META_LEAD)
                ->where('source_reference', $leadId)
                ->first();

            if ($existing !== null) {
                return false;
            }

            $learner = $this->learners->resolve([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => $phone,
                'whatsapp' => $phone,
            ]);

            // Somebody already on the ladder for this programme does not get a
            // second application because they also filled in a Facebook form.
            $onLadder = $learner->applications()
                ->whereNotIn('status', [ApplicationStatus::WITHDRAWN, ApplicationStatus::REJECTED])
                ->exists();

            if ($onLadder) {
                return false;
            }

            Application::create([
                'learner_id' => $learner->id,
                'programme_id' => $programmeId,
                'status' => ApplicationStatus::LEAD,
                'source' => ApplicationSource::META_LEAD,
                'source_reference' => $leadId,
                'campaign' => $campaign,
                'applied_at' => $createdAt ?? now(),
                // Due now. Every one of these has already been waiting since
                // the ad ran, and how long we take to make first contact is
                // the number that decides whether we ever reach them.
                'next_action_at' => now(),
            ]);

            return true;
        });
    }

    // ------------------------------------------------------------- parsing

    /** @return array<string, int> */
    private function mapHeader(array $header): array
    {
        $map = [];

        foreach ($header as $index => $raw) {
            $key = preg_replace('/[^a-z0-9]/', '_', strtolower(trim((string) $raw))) ?? '';
            $key = trim($key, '_');

            foreach (self::COLUMNS as $field => $aliases) {
                if (! isset($map[$field]) && in_array($key, $aliases, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    /** @param  resource  $handle */
    private function sniffDelimiter($handle): string
    {
        $first = fgets($handle);
        rewind($handle);

        if ($first === false) {
            return ',';
        }

        return substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',';
    }

    /** @return array{0: string, 1: string} */
    private function splitName(?string $full, ?string $first, ?string $last): array
    {
        if (($first ?? '') !== '' || ($last ?? '') !== '') {
            return [trim((string) $first), trim((string) $last)];
        }

        $parts = preg_split('/\s+/', trim((string) $full)) ?: [];

        if (count($parts) <= 1) {
            return [trim((string) $full), ''];
        }

        $surname = array_pop($parts);

        return [implode(' ', $parts), $surname];
    }

    /** Meta writes phone numbers as "p:+27821234567". */
    private function stripMetaPrefix(?string $value): ?string
    {
        return $value === null ? null : preg_replace('/^p:/i', '', $value);
    }

    private function parseTime(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function clean(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** Used by the dashboard to offer a programme to file the leads under. */
    public function programmeOptions(): array
    {
        return Programme::orderBy('sort_order')->pluck('name', 'id')->all();
    }
}
