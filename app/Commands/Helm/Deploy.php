<?php

namespace App\Commands\Helm;



use App\Modules\HelmTool\HelmTool;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class Deploy extends Command
{
    protected $signature = 'deploy';

    protected $description = 'Deploy the application';

    public function handle(Config $config, HelmTool $helmTool)
    {
        $this->info('Deploying...');
        $deploy = $config->getDeployConfig();

        $this->info('Deploying Helm chart from ' . $deploy->chartLocal->chartPath);

        $helmTool->putVersionsIfNeeded($deploy);

        $helmTool->deploy($deploy);


    }
}
