<?php

namespace App\Providers\Filament;

use App\Models\User;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BokshornIt\FilamentActivityTimeline\ActivityTimelinePlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
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
            ->path('admin')
            ->login()
            ->brandName('Buku Induk Siswa')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->font('Geist')
            ->monoFont('Geist Mono')
            ->sidebarWidth('15rem')
            ->sidebarCollapsibleOnDesktop()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigationGroups([
                'Buku Induk',
                'Arsip',
                'Persuratan',
                'Master Data',
                'Pengaturan',
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Pengaturan')
                    ->navigationSort(2),
                ActivityTimelinePlugin::make()
                    ->navigationGroup('Pengaturan')
                    ->navigationSort(4)
                    ->dateFormat('d-m-Y')
                    ->dateTimeFormat('d-m-Y H:i')
                    ->causerIcons([User::class => 'heroicon-m-user'])
                    ->events([
                        'backup_dibuat' => ['icon' => 'heroicon-m-circle-stack', 'color' => 'info'],
                        'backup_diunduh' => ['icon' => 'heroicon-m-arrow-down-tray', 'color' => 'warning'],
                        'backup_dihapus' => ['icon' => 'heroicon-m-trash', 'color' => 'danger'],
                    ]),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
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
