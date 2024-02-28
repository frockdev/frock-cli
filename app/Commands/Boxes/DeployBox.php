<?php

namespace App\Commands\Boxes;

use App\CurCom;
use App\Modules\HelmTool\HelmTool;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class DeployBox extends Command
{
    protected $signature = 'box:deploy {boxName} {--render}';

    protected $description = 'Deploy the box or just render template if --render specified';

    public function handle(Config $config, HelmTool $helmTool)
    {
        CurCom::setCommand($this);
        $boxName = $this->argument('boxName');
        $this->info('Trying to deploy box: '.$boxName.'...');
        foreach ($config->getBoxes() as $name => $box) {
            if ($name === $boxName) {
                $deploy = $box;

                $helmTool->putVersionsIfNeeded($deploy);
                if ($deploy->combineFinalValuesFromEnvAndOverrides) {
                    $helmTool->combineValues($deploy);
                }

                if ($this->option('render')) {
                    $this->info('Rendering Helm chart...');
                    $helmTool->render($deploy, $boxName, $config->getWorkingDir());
                    return 0;
                } else {
                    $this->info('Deploying Helm chart...');
                    $helmTool->deploy($deploy, $boxName, $config->getWorkingDir());
                }

                return 0;
            }
        }

        $this->error('Box not found');
        return 404;



    }
}
