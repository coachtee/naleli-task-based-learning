<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Services\Identity\LabPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A paid learner choosing the PIN they will type at a lab computer.
 *
 * The signature on the link is the whole authentication story, exactly as it
 * is for the profile page: it carries the learner id and an expiry, both
 * covered by the app key, so a tampered or lapsed link never reaches this
 * controller. That is the right trade for somebody who has just paid and does
 * not yet have any credential at all — asking them to invent a password
 * before they can reach the course they bought is a wall, not a door.
 */
class WorkspaceAccessController extends Controller
{
    public function __construct(private readonly LabPin $pins) {}

    public function show(Learner $learner): View
    {
        return view('learner.access', [
            'learner' => $learner,
            'workspace' => $this->workspaceUrl(),
            'alreadySet' => $learner->pin_hash !== null,
        ]);
    }

    public function update(Request $request, Learner $learner): RedirectResponse
    {
        $length = LabPin::LENGTH;

        $request->validate([
            'pin' => ['required', 'digits:'.$length, 'confirmed'],
        ], [
            'pin.digits' => "Your PIN must be exactly {$length} numbers.",
            'pin.confirmed' => 'The two PINs you typed are not the same.',
        ]);

        $pin = (string) $request->input('pin');

        // Refused rather than silently allowed. A PIN of 000000 or 123456 on a
        // machine thirty learners share is not a PIN.
        if ($this->tooObvious($pin)) {
            return back()->withErrors([
                'pin' => 'That PIN is too easy to guess. Avoid 123456, 000000, and your date of birth.',
            ]);
        }

        $this->pins->set($learner, $pin);

        return redirect()->route('learner.access.done', ['learner' => $learner->id]);
    }

    /** Shown after the link has been spent, so it carries no signature. */
    public function done(Learner $learner): View
    {
        return view('learner.access-done', [
            'learner' => $learner,
            'workspace' => $this->workspaceUrl(),
        ]);
    }

    private function tooObvious(string $pin): bool
    {
        // All one digit, or a straight run up or down.
        if (preg_match('/^(\d)\1+$/', $pin) === 1) {
            return true;
        }

        $ascending = $descending = true;
        for ($i = 1, $n = strlen($pin); $i < $n; $i++) {
            $step = (int) $pin[$i] - (int) $pin[$i - 1];
            $ascending = $ascending && $step === 1;
            $descending = $descending && $step === -1;
        }

        return $ascending || $descending;
    }

    private function workspaceUrl(): string
    {
        $configured = (string) config('kcs.workspace_url');

        return rtrim($configured !== '' ? $configured : url('/workspace'), '/');
    }
}
