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
});
