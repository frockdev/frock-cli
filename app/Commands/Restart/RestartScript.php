<?php

namespace App\Commands\Restart;

use App\Modules\HelmTool\HelmTool;
use App\Modules\Kubernetes\KubernetesClusterManager;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class RestartScript extends Command
{
    protected $signature = 'restart';

    protected $description = 'Send a restart signal to the server.';

    public function handle(KubernetesClusterManager $kubernetesClusterManager, Config $config): void
    {
        $pod = $kubernetesClusterManager->findPodByLabelsAndNamespace($config->getDevContainerLabels(), $config->getNamespace());
        $pid = $kubernetesClusterManager->getPid($config->getNamespace(), $pod);
        $kubernetesClusterManager->execDevCommand($config->getNamespace(), $pod, ['kill', $pid]);
        $this->info('Restart signal sent.');

    }
}
