<?php

namespace App\Commands\Helm;

use App\CurCom;
use App\Modules\HelmTool\HelmTool;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class Deploy extends Command
{
    protected $signature = 'deploy {--render}';

    protected $description = 'Deploy the application or just render template if --render specified';

    public function handle(Config $config, HelmTool $helmTool)
    {
        CurCom::setCommand($this);
        $this->info('Deploying...');
        $deploy = $config->getDeployConfig();

        $helmTool->putVersionsIfNeeded($deploy);

        if ($this->option('render')) {
            $this->info('Rendering Helm chart');
            $helmTool->render($deploy, $config->getProjectName(), $config->getWorkingDir());
            return;
        } else {

            $boxes = $config->getBoxes();

            foreach ($boxes as $boxName=>$box) {
                if ($config->ifBoxShouldBeAutoDeployed($boxName)) {
                    $this->info('Deploying box: '.$boxName);
                    $helmTool->deploy($box, $boxName, $config->getWorkingDir());
                }
            }

            $this->info('Deploying Helm chart');
            $helmTool->deploy($deploy, $config->getProjectName(), $config->getWorkingDir());

        }

    }
}
