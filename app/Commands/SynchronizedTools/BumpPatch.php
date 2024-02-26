<?php

namespace App\Commands\SynchronizedTools;

use App\Modules\ProjectConfig\Config;
use App\Modules\SynchronizedTools\SynchronizedToolsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BumpPatch extends Command
{
    protected $signature = 'tools:patch {tool?}';

    protected $description = 'Bump patch version of all synchronized tools, or specified one by name';

    public function handle(Config $config, SynchronizedToolsManager $manager)
    {
        $tools = $config->getSynchronizedTools();
        if ($this->argument('tool')) {
            $this->info('Bumping patch version of ' . $this->argument('tool'));
            $version = $manager->findHighestToolPatchVersion($tools[$this->argument('tool')] ?? throw new \Exception('Tool not installed'));
            $config->setNewSynchronizedToolsetVersion($this->argument('tool'), $version);
        } else {
            $this->info('Bumping patch version of all synchronized tools');
            foreach ($tools as $tool) {
                $this->info('Bumping patch version of ' . $tool->name);
                $version = $manager->findHighestToolPatchVersion($tool);
                $config->setNewSynchronizedToolsetVersion($this->argument('tool'), $version);
            }
        }
        Artisan::call('tools:install', [], $this->output);
    }
}
