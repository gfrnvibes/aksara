<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Bendahara\Pages\RingkasanKeuangan;
use App\Filament\GlobalSearch\PencarianPanel;
use App\Http\Middleware\WajibGantiPassword;
use Filament\Actions\Action;
use Filament\Enums\GlobalSearchPosition;
use Filament\Enums\UserMenuPosition;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class BendaharaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('bendahara')
            ->path('bendahara')
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            ->brandName('Aksara | Bendahara')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->font('Geist')
            ->monoFont('Geist Mono')
            ->sidebarWidth('15rem')
            ->sidebarCollapsibleOnDesktop()
            ->topbar(false)
            ->globalSearch(PencarianPanel::class, position: GlobalSearchPosition::Sidebar)
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn () => view('filament.components.pencarian-sidebar'))
            ->userMenuItems([
                Action::make('panelGuru')->label('Buka Panel Guru')->icon(Heroicon::OutlinedAcademicCap)
                    ->url(fn (): string => Filament::getPanel('guru')->getUrl())
                    ->visible(fn (): bool => Filament::auth()->user()?->canAccessPanel(Filament::getPanel('guru')) ?? false),
                Action::make('panelAdmin')
                    ->label('Buka Panel Admin')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->url(fn (): string => Filament::getPanel('admin')->getUrl())
                    ->visible(fn (): bool => Filament::auth()->user()?->canAccessPanel(Filament::getPanel('admin')) ?? false),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Bendahara/Resources'), for: 'App\Filament\Bendahara\Resources')
            ->discoverPages(in: app_path('Filament/Bendahara/Pages'), for: 'App\Filament\Bendahara\Pages')
            ->pages([
                RingkasanKeuangan::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Bendahara/Widgets'), for: 'App\Filament\Bendahara\Widgets')
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
