<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Http\Middleware\WajibGantiPassword;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The teacher panel: a short menu with only the teacher's own work — their classes, their subjects' grades and
 * the report cards of the class they look after.
 */
class GuruPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('guru')
            ->path('guru')
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            ->brandName('AKSARA | GURU')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->font('Geist')
            ->monoFont('Geist Mono')
            ->sidebarWidth('15rem')
            ->userMenuItems([
                Action::make('panelAdmin')
                    ->label('Buka Panel Admin')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->url(fn (): string => Filament::getPanel('admin')->getUrl())
                    ->visible(fn (): bool => Filament::auth()->user()?->canAccessPanel(Filament::getPanel('admin')) ?? false),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Guru/Resources'), for: 'App\Filament\Guru\Resources')
            ->discoverPages(in: app_path('Filament/Guru/Pages'), for: 'App\Filament\Guru\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Guru/Widgets'), for: 'App\Filament\Guru\Widgets')
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
                WajibGantiPassword::class,
            ]);
    }
}
