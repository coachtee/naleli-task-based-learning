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
