<?php

namespace App\Commands\SynchronizedTools;

use App\Modules\ProjectConfig\Config;
use App\Modules\SynchronizedTools\SynchronizedToolsManager;
use Illuminate\Console\Command;

class InstallTools extends Command
{
    protected $signature = 'tools:install';
    protected $description = 'Download and install all synchronized tools';

    public function handle(Config $config, SynchronizedToolsManager $synchronizedToolsManager) {
        $tools = $config->getSynchronizedTools();

        $synchronizedToolsManager->cleanAllTools();

        foreach ($tools as $tool) {
            $this->info('Installing ' . $tool->name);
            $synchronizedToolsManager->installTool($tool);
        }
        $this->info('All tools installed');
    }
}
