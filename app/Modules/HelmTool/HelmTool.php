<?php

namespace App\Modules\HelmTool;

use App\Modules\ConfigObjects\Deploy;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class HelmTool
{
    public function purge() {

    }

    public function deploy(Deploy $deploy) {
        $this->upgradeWithInstall($deploy);
    }

    public function putVersionsIfNeeded(Deploy $deploy)
    {
        if ($deploy->chartLocal===null || ($deploy->chartLocal->appVersion === null && $deploy->chartLocal->chartVersion ===null)) return;

        if (file_exists($deploy->chartLocal->chartPath . '/Chart.example.yaml')) {
            shell_exec('rm -rf ' . $deploy->chartLocal->chartPath . '/Chart.yaml');
            shell_exec('cp ' . $deploy->chartLocal->chartPath . '/Chart.example.yaml ' . $deploy->chartLocal->chartPath . '/Chart.yaml');
        } elseif (!file_exists($deploy->chartLocal->chartPath . '/Chart.yaml')) {
            touch($deploy->chartLocal->chartPath . '/Chart.yaml');
        }
        $filename = $deploy->chartLocal->chartPath . '/Chart.yaml';

        $parsed = Yaml::parseFile($filename);
        if ($deploy->chartLocal->appVersion !== null) {
            $parsed['appVersion'] = $deploy->chartLocal->appVersion;
        }
        if ($deploy->chartLocal->chartVersion !== null) {
            $parsed['version'] = $deploy->chartLocal->chartVersion;
        }
        file_put_contents($filename, Yaml::dump($parsed));

    }

    private function upgradeWithInstall(Deploy $deploy)
    {
        if ($deploy->chartLocal === null) {

        } else {
            $values = '';
            if (file_exists($deploy->valuesByEnv . '/' . $deploy->appEnvironment . '.values.yaml')) {
                $values = '-f '.$deploy->valuesByEnv . '/' . $deploy->appEnvironment . '.values.yaml';
            }
            if (file_exists($deploy->valuesByEnv . '/secrets.values.yaml')) {
                $values .= ' -f '.$deploy->valuesByEnv . '/secrets.values.yaml';
            }
            if (file_exists($deploy->valuesByEnv . '/overrides.values.yaml')) {
                $values .= ' -f '.$deploy->valuesByEnv . '/overrides.values.yaml';
            }

            $cmd = ['helm', 'upgrade', '-n', $deploy->namespace, '--install'];
            foreach (explode(' ', $values) as $word) {
                $cmd[] = $word;
            }
            $cmd[] = $deploy->chartLocal->chartPath;
            $process = new Process($cmd);
            $process->setTty(true);
            $process->run();
        }


    }
}
