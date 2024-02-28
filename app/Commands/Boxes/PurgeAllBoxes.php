<?php

namespace App\Commands\Boxes;

use App\Modules\HelmTool\HelmTool;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class PurgeAllBoxes extends Command
{
    protected $signature = 'box:purge-all';

    protected $description = 'Purge all boxes';

    public function handle(HelmTool $helmTool, Config $config)
    {
        $this->info('Purging all boxes...');

        foreach ($config->getBoxes() as $name => $box) {
            $helmTool->purge($box, $name);
            $this->info('Purged box: ' . $name . '!');
        }

        $this->info('Purged all boxes!');

        return 0;

    }

}
