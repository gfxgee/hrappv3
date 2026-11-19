<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\TeamsPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    TeamsPanelProvider::class,
    FortifyServiceProvider::class,
];
