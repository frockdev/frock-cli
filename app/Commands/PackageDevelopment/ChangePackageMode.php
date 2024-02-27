<?php

namespace App\Commands\PackageDevelopment;

use App\Modules\PackagesTool\DevelopmentPackagesManager;
use Illuminate\Console\Command;

class ChangePackageMode extends Command
{
    protected $signature = 'package:mode {package}';

    protected $description = 'Change package development mode on/off';

    public function handle(\App\Modules\ProjectConfig\Config $config, DevelopmentPackagesManager $developmentPackagesManager)
    {
        $package = $this->argument('package');
        $packages = $config->getDevelopmentPackages();
        $currentPackage = $packages[$package] ?? null;

        if (!$currentPackage) {
            $this->error('Package not found');
            return;
        }

        $enabled = $developmentPackagesManager->detectIfPackageEnabledForDeveloping($currentPackage);

        if ($enabled) {
            $this->info('Re-installing Package in normal mode');
            $developmentPackagesManager->installPackageInNormalMode($currentPackage);
        } else {
            $this->info('Enabling package for development');
            $developmentPackagesManager->enablePackageForDeveloping($currentPackage);
        }


    }
}
