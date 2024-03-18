<?php

namespace App\Commands\SynchronizedTools;

use App\Modules\ProjectConfig\Config;
use App\Modules\SynchronizedTools\SynchronizedToolsManager;
use Illuminate\Console\Command;

class InstallTools extends Command
{
    protected $signature = 'tools:install {tool?}';
    protected $description = 'Download and install all synchronized tools';

    public function handle(Config $config, SynchronizedToolsManager $synchronizedToolsManager) {
        $tools = $config->getSynchronizedTools();

        if (!$this->argument('tool')) {
            $synchronizedToolsManager->cleanAllTools();

            foreach ($tools as $tool) {
                $this->info('Installing ' . $tool->name);
                $synchronizedToolsManager->installTool($tool);
            }
            $this->info('All tools installed');
        } else {
            $synchronizedToolsManager->cleanToolByName($this->argument('tool'));
            $this->info('Installing ' . $this->argument('tool'));
            $synchronizedToolsManager->installTool($tools[$this->argument('tool')] ?? throw new \Exception('Tool not installed'));
            $this->info('Tool installed');
        }
    }
}
