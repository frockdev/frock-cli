<?php

namespace App\Commands\SynchronizedTools;

use App\Modules\ProjectConfig\Config;
use App\Modules\SynchronizedTools\SynchronizedToolsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BumpMinor extends Command
{
    protected $signature = 'tools:minor {tool?}';

    protected $description = 'Bump minor version of all synchronized tools, or specified one by name';

    public function handle(Config $config, SynchronizedToolsManager $manager)
    {
        $tools = $config->getSynchronizedTools();
        if ($this->argument('tool')) {
            $this->info('Bumping minor version of ' . $this->argument('tool'));
            $version = $manager->findHighestToolMinorVersion($tools[$this->argument('tool')] ?? throw new \Exception('Tool not installed'));
            $config->setNewSynchronizedToolsetVersion($this->argument('tool'), $version);
        } else {
            $this->info('Bumping minor version of all synchronized tools');
            foreach ($tools as $tool) {
                $this->info('Bumping minor version of ' . $tool->name);
                $version = $manager->findHighestToolMinorVersion($tool);
                $config->setNewSynchronizedToolsetVersion($tool->name, $version);
            }
        }
        Artisan::call('tools:install', [], $this->output);
    }
}
