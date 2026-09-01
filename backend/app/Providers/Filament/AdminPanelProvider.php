<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\CallQueue;
use App\Filament\Widgets\PipelineOverview;
use App\Filament\Widgets\WorkQueue;
use App\Support\LocalAvatarProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // Empty in production: the front controller already sits in
            // public_html/admin, so the panel mounts at that directory's root.
            ->path(config('kcs.panel_path'))
            ->login()
            ->brandName('KCS Education')
            // The NIBS navy and orange the learner app already uses, so staff
            // and learners are visibly looking at one system rather than two
            // products that happen to share a database.
            //
            // The ramp is given explicitly rather than via Color::hex(), which
            // keeps only the hue of what it is passed and regenerates the
            // lightness steps — handing #0A1140 to it produced a mid indigo,
            // not navy. These are real NIBS navy values, so shade 600 (what
            // buttons use) is the colour the brand actually specifies.
            ->colors([
                'primary' => [
                    50 => '#F2F4FA', 100 => '#E4E8F4', 200 => '#C7CEE7',
                    300 => '#9BA6CE', 400 => '#6B7AAE', 500 => '#46568F',
                    600 => '#2E3D73', 700 => '#222E5C', 800 => '#16204A',
                    900 => '#0F1740', 950 => '#0A1140',
                ],
                'warning' => Color::hex('#F05A00'),
                'success' => Color::hex('#059669'),
                'danger' => Color::hex('#DC2626'),
                'info' => Color::Sky,
                'gray' => Color::Slate,
            ])
            // Filament's default avatars are fetched from an external service,
            // which sends staff names off-site and renders broken wherever
            // outbound traffic is restricted. Initials are drawn locally.
            ->defaultAvatarProvider(LocalAvatarProvider::class)
            ->font('Archivo')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            // Two groups, not four, and the split is by whether a person
            // does anything there. Nine menu items across Admissions, Money,
            // Delivery and Catalogue read as nine equal jobs; in fact only
            // three are places staff work. The rest are what the system
            // produced — tokens, entitlements, payments, the catalogue — and
            // they are worth looking up, never worth doing.
            ->navigationGroups([
                NavigationGroup::make('Do the work')->icon('heroicon-o-inbox-arrow-down'),
                NavigationGroup::make('Records')->icon('heroicon-o-archive-box'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PipelineOverview::class,
                // First on the screen, because it is first in the day.
                CallQueue::class,
                WorkQueue::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
