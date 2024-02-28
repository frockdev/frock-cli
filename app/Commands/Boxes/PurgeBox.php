<?php

namespace App\Commands\Boxes;

use App\Modules\HelmTool\HelmTool;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class PurgeBox extends Command
{
    protected $signature = 'box:purge {boxName}';

    protected $description = 'Purge the box';

    public function handle(HelmTool $helmtool, Config $config)
    {
        $this->info('Purging box...');

        $boxName = $this->argument('boxName');
        foreach ($config->getBoxes() as $name=>$box) {
            if ($name === $boxName) {
                $helmtool->purge($box, $boxName);
                $this->info('Purged!');
                return 0;
            }
        }

        return 404;
    }
}
