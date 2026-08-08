<?php

namespace Alfism1\FilamentLogManagement;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentLogManagementServiceProvider extends PackageServiceProvider
{
    public const NAME = 'filament-log-management';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::NAME)
            ->hasConfigFile('log-management')
            ->hasViews()
            ->hasTranslations();
    }
}
