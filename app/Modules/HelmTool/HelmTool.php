<?php

namespace App\Modules\HelmTool;

use App\Modules\ConfigObjects\Deploy;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class HelmTool
{
    public function purge(Deploy $deploy, string $projectName ) {
        $cmd = ['helm', 'uninstall', '-n', $deploy->namespace, $projectName.'-'.$deploy->appEnvironment];
        $process = new Process($cmd);
        $process->setTty(true);
        $process->run();
        $cmd = ['kubectl', 'delete', 'ns', $deploy->namespace];
        $process = new Process($cmd);
        $process->setTty(true);
        $process->run();
    }

    public function deploy(Deploy $deploy, string $projectName, string $workingDirectory) {
        if ($deploy->chartLocal === null) {

        } else {
            if (file_exists($deploy->valuesByEnv . '/final.values.yaml')) {
                $values = '-f '.$deploy->valuesByEnv . '/final.values.yaml';
            } else {
                throw new \Exception('final.values.yaml does not exist. Please run helm:create-values command.');
            }

            if ($deploy->chartLocal->valuesKeyOfLocalDirectory) {
                $values.= ' --set '.$deploy->chartLocal->valuesKeyOfLocalDirectory.'=' . $workingDirectory;
            }

            $cmd = ['helm', 'upgrade', '--create-namespace', '-n', $deploy->namespace, '--install', $projectName.'-'.$deploy->appEnvironment];
            foreach (explode(' ', $values) as $word) {
                $cmd[] = $word;
            }
            $cmd[] = $deploy->chartLocal->chartPath;
            $process = new Process($cmd);
            $process->setTty(true);
            $process->run();
        }
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

    public function combineValues(Deploy $deploy)
    {
        $envPath = $deploy->valuesByEnv;
        $currentEnv = $deploy->appEnvironment;
        $commonParsed = Yaml::parseFile($envPath . '/common.values.yaml', YAML::PARSE_OBJECT);
        if (!$commonParsed) {
            $commonParsed = [];
        }
        $valuesParsed = Yaml::parseFile($envPath . '/' . $currentEnv . '.values.yaml', YAML::PARSE_OBJECT);
        if (!$valuesParsed) {
            $valuesParsed = [];
        }
        $secretsParsed = Yaml::parseFile($envPath . '/secrets.values.yaml', YAML::PARSE_OBJECT);
        if (!$secretsParsed) {
            $secretsParsed = [];
        }
        $overridesParsed = Yaml::parseFile($envPath . '/overrides.values.yaml', YAML::PARSE_OBJECT);
        if (!$overridesParsed) {
            $overridesParsed = [];
        }
        $values = array_replace_recursive(
            $commonParsed,
            $valuesParsed,
            $secretsParsed,
            $overridesParsed
        );
        $dump = Yaml::dump($values, 8, 2);
        file_put_contents($envPath . '/final.values.yaml', $dump);


    }
}
