<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\BendaharaPanelProvider;
use App\Providers\Filament\GuruPanelProvider;
use App\Providers\Filament\UjianPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    GuruPanelProvider::class,
    UjianPanelProvider::class,
    BendaharaPanelProvider::class,
    FortifyServiceProvider::class,
];
