<?php

namespace App\Commands\Boxes;

use App\Modules\HelmTool\HelmTool;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class DeployAllBoxes extends Command
{
    protected $signature = 'box:deploy-all';

    protected $description = 'Deploy all boxes';

    public function handle(HelmTool $helmTool, Config $config) {

        foreach ($config->getBoxes() as $boxName=>$box) {
            $deploy = $box;

            $helmTool->putVersionsIfNeeded($deploy);
            if ($deploy->combineFinalValuesFromEnvAndOverrides) {
                $helmTool->combineValues($deploy);
            }

            if ($this->option('render')) {
                $this->info('Rendering Helm chart...');
                $helmTool->render($deploy, $boxName, $config->getWorkingDir());

            } else {
                $this->info('Deploying Helm chart...');
                $helmTool->deploy($deploy, $boxName, $config->getWorkingDir());

            }
        }
        return 0;
    }

}
