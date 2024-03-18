<?php

namespace App\Modules\Kubernetes;

use App\CurCom;
use App\Modules\ProjectConfig\Config;
use Symfony\Component\Process\Process;

class KubernetesClusterManager
{
    private Config $config;

    public function __construct(Config $config)
    {

        $this->config = $config;
    }

    public function findPodByLabelsAndNamespace(array $labels, string $namespace): string {

        $labelsString = '';
        foreach ($labels as $key=>$value) {
            $labelsString .= $key.'='.$value.',';
        }
        $labelsString = rtrim($labelsString, ',');

        $cmd = 'kubectl -n '.$namespace.' get pods --selector '.$labelsString.' -o jsonpath=\'{.items[0].metadata.name}\'';
        $process = new Process(explode(' ', $cmd));
        $process->run();
        $podName = $process->getOutput();

        if (!$podName) {
            throw new \Exception('Pod with labels '.$labelsString.' not found in namespace '.$namespace);
        }

        return trim($podName, "'");
    }

    public function getPid(string $namespace, string $podName) {
        $runnable = $this->config->getKubectlExecCommand($namespace, $podName);


        $command = 'cat /.devspace/devspace-pid';

        $runnable = [...$runnable, $command];

        $process = new Process($runnable);
        $process->setIdleTimeout(null);
        $process->setTimeout(null);
        $process->run();

        return (int)$process->getOutput();
    }

    public function execDevCommand(string $namespace, string $podName, array $command, bool $debug = false) {
        $runnable = $this->config->getKubectlExecCommand($namespace, $podName);

        if ($debug) {
            $command = [...$this->config->getDebugEnvExportForCommands(), '&&', ...$command];
        }

        if ($this->config->getDevWorkingDir()) {
            $command = ['cd', $this->config->getDevWorkingDir(), '&&', ...$command];
        }

        $command = implode(' ', $command);

        $runnable = [...$runnable, $command];

        $process = new Process($runnable);
        echo 'TTY: '.(bool)$this->config->getTtyEnabled().PHP_EOL;
        $process->setTty($this->config->getTtyEnabled());
        $process->setIdleTimeout(null);
        $process->setTimeout(null);
        $process->run();
        if (!$this->config->getTtyEnabled()) {
            if ($process->isSuccessful()) {
                echo $process->getOutput();
            } else {
                echo $process->getOutput();
                echo $process->getErrorOutput();
                exit($process->getExitCode());
            }
        }
    }

}
