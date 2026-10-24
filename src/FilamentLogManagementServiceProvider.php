<?php

namespace Alfism1\FilamentLogManagement;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentLogManagementServiceProvider extends PackageServiceProvider
{
    public const NAME = 'filament-log-management';

    public const PACKAGE = 'alfism1/filament-log-management';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::NAME)
            ->hasConfigFile('log-management')
            ->hasViews()
            ->hasTranslations();
    }

    /**
     * Filament 4's theme is compiled without scanning plugin views, so the
     * page ships its own stylesheet. `php artisan filament:assets` publishes it.
     */
    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make(static::NAME, __DIR__ . '/../resources/dist/filament-log-management.css'),
        ], package: static::PACKAGE);
    }
}
