<?php

namespace App\Commands\SynchronizedTools;

use App\Modules\ProjectConfig\Config;
use App\Modules\SynchronizedTools\SynchronizedToolsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BumpMajor extends Command
{
    protected $signature = 'tools:major {tool?}';

    protected $description = 'Bump major version of all synchronized tools, or specified one by name';

    public function handle(Config $config, SynchronizedToolsManager $manager)
    {
        $tools = $config->getSynchronizedTools();
        if ($this->argument('tool')) {
            $this->info('Bumping major version of ' . $this->argument('tool'));
            $version = $manager->findHighestToolMajorVersion($tools[$this->argument('tool')] ?? throw new \Exception('Tool not installed'));
            $config->setNewSynchronizedToolsetVersion($this->argument('tool'), $version);
        } else {
            $this->info('Bumping major version of all synchronized tools');
            foreach ($tools as $tool) {
                $this->info('Bumping major version of ' . $tool->name);
                $version = $manager->findHighestToolMajorVersion($tool);
                $config->setNewSynchronizedToolsetVersion($this->argument('tool'), $version);
            }
        }
        Artisan::call('tools:install', [], $this->output);
    }
}
