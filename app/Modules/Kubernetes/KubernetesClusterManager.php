<?php

namespace App\Modules\Kubernetes;

use App\Modules\ProjectConfig\Config;
use Symfony\Component\Process\Process;

class KubernetesClusterManager
{
    private Config $config;

    public function __construct(Config $config)
    {

        $this->config = $config;
    }

    public function findPodByLabelAndNamespace(string $labelKey, string $labelValue, string $namespace): string {
        $cmd = 'kubectl -n '.$namespace.' get pods -l '.$labelKey.'='.$labelValue.' -o jsonpath=\'{.items[0].metadata.name}\'';
        $process = new Process(explode(' ', $cmd));
        $process->run();
        $podName = $process->getOutput();

        if (!$podName) {
            throw new \Exception('Pod with label '.$labelKey.'='.$labelValue.' not found in namespace '.$namespace);
        }

        return trim($podName, "'");
    }

    public function execDevCommand(string $namespace, string $podName, string $command, bool $debug = false) {
        $exec = $this->config->getKubectlExecCommand();

        if ($debug) {
            $command = $this->config->getDebugEnvExportForCommands().' && '.$command;
        }

        if ($this->config->getDevWorkingDir()) {
            $command = 'cd '.$this->config->getDevWorkingDir().' && '.$command;
        }

        $cmd = sprintf($exec, $namespace, $podName);
        $explodedCmd = explode(' ', $cmd);
        $explodedCmd[] = $command;
        echo implode(' ', $explodedCmd)."\n";
        $process = new Process($explodedCmd);
        $process->setIdleTimeout(null);
        $process->setTimeout(null);
        $process->setTty(true);
        $process->run();
    }

}
