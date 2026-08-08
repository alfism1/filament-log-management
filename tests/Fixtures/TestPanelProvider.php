<?php

namespace Alfism1\FilamentLogManagement\Tests\Fixtures;

use Alfism1\FilamentLogManagement\FilamentLogManagementPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilamentLogManagementPlugin::make());
    }
}
