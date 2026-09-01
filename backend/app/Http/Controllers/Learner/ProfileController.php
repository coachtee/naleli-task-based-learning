<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Enums\IdType;
use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Services\Identity\LearnerRegistry;
use App\Services\Registration\ProfileCompleteness;
use App\Support\Normalise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * The learner finishing their own registration.
 *
 * Everything the school needs after the money — identity, date of birth,
 * address, schooling, employment — used to have nowhere to be entered, so a
 * registrar had to phone and type it in. This is the page the secure link
 * opens.
 *
 * There is no login. The signed link IS the credential, which is the right
 * trade for somebody who has just paid and should not be asked to invent a
 * password to hand us their own address. The signature middleware refuses a
 * tampered or expired link before this controller runs.
 */
class ProfileController extends Controller
{
    private const PROVINCES = [
        'Gauteng', 'KwaZulu-Natal', 'Western Cape', 'Eastern Cape', 'Free State',
        'Limpopo', 'Mpumalanga', 'North West', 'Northern Cape',
    ];

    private const QUALIFICATIONS = [
        'Still at school', 'Grade 10', 'Grade 11', 'Matric (Grade 12)',
        'Certificate', 'Diploma', 'Degree', 'Postgraduate', 'Other',
    ];

    private const EMPLOYMENT = [
        'Unemployed', 'Employed full-time', 'Employed part-time',
        'Self-employed', 'Studying', 'Other',
    ];

    public function show(Request $request, Learner $learner, ProfileCompleteness $profiles): View
    {
        return view('learner.profile', [
            'learner' => $learner,
            'missing' => $profiles->missing($learner),
            'percent' => $profiles->percent($learner),
            'complete' => $profiles->isComplete($learner),
            'provinces' => self::PROVINCES,
            'qualifications' => self::QUALIFICATIONS,
            'employment' => self::EMPLOYMENT,
            'idTypes' => IdType::cases(),
            'action' => $request->fullUrl(),
        ]);
    }

    public function update(Request $request, Learner $learner, ProfileCompleteness $profiles): RedirectResponse
    {
        $data = $request->validate([
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:24'],
            'email' => ['nullable', 'email', 'max:190'],
            'address_line' => ['nullable', 'string', 'max:160'],
            'suburb' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:60'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'highest_qualification' => ['nullable', 'string', 'max:120'],
            'school_or_institution' => ['nullable', 'string', 'max:160'],
            'employment_status' => ['nullable', 'string', 'max:60'],
            'id_type' => ['nullable', 'string', 'max:24'],
            'id_number' => ['nullable', 'string', 'max:24'],
        ]);

        $idNumber = $data['id_number'] ?? null;
        $idType = $data['id_type'] ?? null;
        unset($data['id_number'], $data['id_type']);

        // A blank field means "I have not filled this in yet", never "delete
        // what the school already has". Half a form saved on a taxi's data
        // must not wipe the other half.
        $data = array_filter($data, static fn ($v): bool => $v !== null && $v !== '');

        if (isset($data['phone'])) {
            $data['phone'] = Normalise::phone($data['phone']);
        }

        try {
            DB::transaction(function () use ($learner, $data, $idNumber, $idType, $profiles): void {
                $learner->fill($data)->save();

                // Identity goes through the registry, never straight onto the
                // model: it is what encrypts the number, hashes it for
                // matching, masks it for the dashboard, and refuses an ID that
                // already belongs to somebody else.
                if ($idNumber !== null && $idNumber !== '' && $learner->id_number_hash === null) {
                    app(LearnerRegistry::class)->attachIdentity($learner, $idNumber, $idType);
                }

                // Recomputes completeness AND moves the application off
                // profile_incomplete once nothing is outstanding — finishing
                // the profile is what finishes the registration.
                $learner->refresh();
                $profiles->settleApplication($learner->applications()->latest('id')->first(), $learner);
            });
        } catch (RuntimeException $e) {
            return redirect($request->fullUrl())
                ->withInput()
                ->withErrors(['id_number' => $e->getMessage()]);
        }

        return redirect($request->fullUrl())
            ->with('saved', 'Your details have been saved.');
    }
}
