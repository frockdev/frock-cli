<?php

namespace App\Commands\Helm;

use App\Modules\HelmTool\HelmTool;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class Purge extends Command
{
    protected $signature = 'down';

    protected $description = 'Purge the application';

    public function handle(HelmTool $helmtool, Config $config) {
        $this->info('Purging...');

        $helmtool->purge($config->getDeployConfig(), $config->getProjectName());

        $boxes = $config->getBoxes();

        foreach ($boxes as $boxName=>$box) {
            if ($config->ifBoxShouldBeAutoDeployed($boxName)) {
                if ($config->getDeployConfig()->namespace==$box->namespace) {
                    continue;
                }
                $this->info('Purging box: '.$boxName);
                $helmtool->purge($box, $boxName);
            }
        }
        $this->info('Purged!');
    }
}
