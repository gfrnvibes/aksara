<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Models\User;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BokshornIt\FilamentActivityTimeline\ActivityTimelinePlugin;
use Filament\Actions\Action;
use Filament\Enums\GlobalSearchPosition;
use Filament\Enums\UserMenuPosition;
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
            ->login(Login::class)
            ->brandName('Aksara')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->font('Geist')
            ->monoFont('Geist Mono')
            ->sidebarWidth('14rem') // Diperlebar di layar besar oleh theme.css.
            ->sidebarCollapsibleOnDesktop()
            ->topbar(false)
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->userMenuItems([
                Action::make('panelBendahara')->label('Buka Panel Bendahara')->icon(Heroicon::OutlinedBanknotes)
                    ->url(fn (): string => Filament::getPanel('bendahara')->getUrl())
                    ->visible(fn (): bool => Filament::auth()->user()?->canAccessPanel(Filament::getPanel('bendahara')) ?? false),
                Action::make('panelGuru')
                    ->label('Buka Panel Guru')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->url(fn (): string => Filament::getPanel('guru')->getUrl())
                    ->visible(fn (): bool => Filament::auth()->user()?->canAccessPanel(Filament::getPanel('guru')) ?? false),
            ])
            ->navigationGroups([
                'Buku Induk',
                'Kurikulum',
                'Arsip',
                'Persuratan',
                'Kepegawaian',
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
                // AccountWidget::class,
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
