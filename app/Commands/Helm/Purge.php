<?php

namespace App\Commands\Helm;

use App\Modules\HelmTool\HelmTool;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class Purge extends Command
{
    protected $signature = 'purge';

    protected $description = 'Purge the application';

    public function handle(HelmTool $helmtool, Config $config) {
        $this->info('Purging...');

        $helmtool->purge($config->getDeployConfig(), $config->getProjectName());
        $this->info('Purged!');
    }
}
