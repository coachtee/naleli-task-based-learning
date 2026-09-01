<?php

declare(strict_types=1);

/*
 * This application has no public web pages. It is a staff dashboard plus the
 * API the learner app calls, and Filament owns the panel's routes.
 *
 * Laravel's default `Route::get('/', fn () => view('welcome'))` used to sit
 * here, and in production it broke the dashboard: the front controller is
 * served from public_html/admin, so the panel mounts at the directory root,
 * and the welcome stub had already claimed that URI. kcs.edu.za/admin returned
 * 405 while every resource beneath it worked. Leave this file empty.
 */

use App\Http\Controllers\Learner\ProfileController;
use App\Http\Controllers\Learner\WorkspaceAccessController;
use App\Http\Controllers\Workspace\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
 * The one public thing this application serves: a learner finishing their own
 * registration from a link we sent them.
 *
 * `signed` is the whole authentication story. The link carries the learner id
 * and an expiry, both covered by a signature over the app key, so a tampered
 * or lapsed link is refused by middleware before any controller runs. That is
 * the right trade for somebody who has just paid: no password to invent
 * before they can hand us their own address.
 */
Route::middleware('signed')->group(function (): void {
    Route::get('/my/profile/{learner}', [ProfileController::class, 'show'])
        ->name('learner.profile.show');

    Route::post('/my/profile/{learner}', [ProfileController::class, 'update'])
        ->name('learner.profile.update');

    // Choosing a workspace PIN. Same signature-as-credential story: this is
    // the first thing a learner does after paying, and they have nothing to
    // log in with yet.
    Route::get('/my/start/{learner}', [WorkspaceAccessController::class, 'show'])
        ->name('learner.access.show');

    Route::post('/my/start/{learner}', [WorkspaceAccessController::class, 'update'])
        ->name('learner.access.update');
});

// Shown after the signed link has been spent, so it carries no signature of
// its own — and gives away nothing a learner who just set their PIN does not
// already know.
Route::get('/my/start/{learner}/done', [WorkspaceAccessController::class, 'done'])
    ->name('learner.access.done');

/*
 * The learner workspace: the same work as the Android app, in a browser.
 *
 * A lab PC cannot install an APK and a signed .exe costs money the school
 * does not have, so the desktop experience is an installable web app —
 * Edge is already on every machine, "Install this site as an app" gives it a
 * Start-menu entry and its own window, and updating it is a deploy rather
 * than a visit to thirty computers.
 *
 * Everything lives under /workspace/ so the website can rewrite one path onto
 * the application, and so the service worker's scope covers the whole app and
 * nothing else on the domain.
 */
Route::prefix('workspace')->group(function (): void {
    Route::get('/', [WorkspaceController::class, 'shell'])->name('workspace.shell');
    Route::get('/sw.js', [WorkspaceController::class, 'serviceWorker'])->name('workspace.sw');
    Route::get('/manifest.webmanifest', [WorkspaceController::class, 'manifest'])->name('workspace.manifest');
    Route::get('/icon.svg', [WorkspaceController::class, 'icon'])->name('workspace.icon');
});
