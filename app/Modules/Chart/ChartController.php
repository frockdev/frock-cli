<?php

namespace App\Modules\Chart;

use App\Modules\Exec\Exec;
use App\Modules\ProjectConfig\Config;

class ChartController
{

    public function __construct(
        private Config $config
    )
    {
    }

    public function installSpecifiedVersionOfChart(string $version)
    {
        Exec::run('rm -rf helm && rm -rf helm-cloned');
        $helmChartRepository = $this->config->getHelmChartRepository();
        Exec::run('git clone "'.$helmChartRepository.'" helm-cloned');
        Exec::run('cd helm-cloned && git checkout '.$version.' > /dev/null 2>&1 && cd ../');
        Exec::run('rm -rf helm && mv helm-cloned helm && rm -rf helm/.git && rm -rf helm/.github');
    }
}
