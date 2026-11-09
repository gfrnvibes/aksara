<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\BendaharaPanelProvider;
use App\Providers\Filament\GuruPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    GuruPanelProvider::class,
    BendaharaPanelProvider::class,
    FortifyServiceProvider::class,
];
