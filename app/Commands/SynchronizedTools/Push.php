<?php

namespace App\Commands\SynchronizedTools;

use App\Modules\ProjectConfig\Config;
use App\Modules\SynchronizedTools\SynchronizedToolsManager;
use Illuminate\Console\Command;

class Push extends Command
{
    protected $signature = 'tools:push {tool?}';

    protected $description = 'Push all synchronized tools, or specified one by name';

    public function handle(Config $config, SynchronizedToolsManager $manager)
    {
        $tools = $config->getSynchronizedTools();
        if ($this->argument('tool')) {
            $this->info('Pushing ' . $this->argument('tool'));
            $manager->push($tools[$this->argument('tool')] ?? throw new \Exception('Tool not installed'));
        } else {
            $this->info('Pushing all synchronized tools');
            foreach ($tools as $tool) {
                $this->info('Pushing ' . $tool->name);
                $manager->push($tool);
            }
        }
    }
}
