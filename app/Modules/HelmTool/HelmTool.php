<?php

namespace App\Modules\HelmTool;

use App\CurCom;
use App\Modules\ConfigObjects\Deploy;
use App\Modules\ProjectConfig\Config;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class HelmTool
{

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function purge(Deploy $deploy, string $entityToPurge ) {
        if ($this->config->getNeedCreateRancherNamespace()) {

            $response = Http::withHeader('Authorization', 'Bearer '.$this->config->getRancherToken())
                ->delete($this->config->getRancherFullUrl().'/v3/cluster/'.$this->config->getRancherClusterId().'/namespaces/'.$deploy->namespace);
            if ($response->status()!=200) {
                echo $response->body();
                $response->throw();
            }
        }
        $cmd = ['helm', 'uninstall', '-n', $deploy->namespace, $entityToPurge.'-'.$deploy->appEnvironment];
        $process = new Process($cmd);
        $process->setTty($this->config->getTtyEnabled());
        $process->run();
        if (!$this->config->getTtyEnabled()) {
            if ($process->isSuccessful()) {
                echo $process->getOutput()."\n";
            } else {
                echo $process->getOutput()."\n";
                echo $process->getErrorOutput()."\n";
                exit($process->getExitCode());
            }
        }
        $cmd = ['kubectl', 'delete', 'ns', $deploy->namespace];
        $process = new Process($cmd);
        $process->setTty($this->config->getTtyEnabled());
        $process->run();
        if (!$this->config->getTtyEnabled()) {
            if ($process->isSuccessful()) {
                echo $process->getOutput()."\n";
            } else {
                echo $process->getOutput()."\n";
                echo $process->getErrorOutput()."\n";
                exit($process->getExitCode());
            }
        }
    }

    private function valuesCmdBuilding(Deploy $deploy, string $workingDirectory) {
        $valuesCmd = '-f '.$deploy->finalValuesFilePath;

        CurCom::get()->info('Loading Values from: '.$deploy->finalValuesFilePath);

        if (isset($deploy->chartLocal->valuesKeyOfLocalDirectory)) {
            if ($deploy->chartLocal->valuesKeyOfLocalDirectory) {
                $valuesCmd.= ' --set '.$deploy->chartLocal->valuesKeyOfLocalDirectory.'=' . $deploy->chartLocal->localDirectoryPrefix.$workingDirectory;
            }
        }

        foreach ($deploy->redefineValuesFromYamlFiles as $redefineValuesFromYamlFile) {
            $fileParsed = Yaml::parseFile($this->config->getWorkingDir().'/'.$redefineValuesFromYamlFile->yamlFile);
            foreach ($redefineValuesFromYamlFile->fromYamlToHelm as $yamlKey => $helmKey) {
                CurCom::get()->info('We are redefining '.$helmKey.' from '.$redefineValuesFromYamlFile->yamlFile.' to '.Arr::get($fileParsed, $yamlKey));
                $valuesCmd.= ' --set '.$helmKey.'=' . Arr::get($fileParsed, $yamlKey);
            }
        }

        foreach ($deploy->redefineValuesFromEnvVariables as $envVariable => $helmKey) {
            if (getenv($envVariable)) {
                CurCom::get()->info('We are redefining '.$helmKey.' from environment variable '.$envVariable.' to '.getenv($envVariable));
                $valuesCmd.= ' --set '.$helmKey.'=' . getenv($envVariable);
            }
        }

        return $valuesCmd;
    }

    public function deploy(Deploy $deploy, string $installableEntityName, string $workingDirectory) {

        $this->combineValues($deploy);

        if ($deploy->chartLocal === null) {
            $repoName = $this->config->getProjectName().'-'.$installableEntityName;

            CurCom::get()->info('Working with remote repo: '.$repoName);
            $values = $this->valuesCmdBuilding($deploy, $workingDirectory);

            //helm repo add
            CurCom::get()->info('Removing old repo: '.$repoName);
            $cmd = ['helm', 'repo', 'remove', $repoName, $deploy->chartRemote->repoUrl];
            $process = new Process($cmd);
            $process->run();

            CurCom::get()->info('Adding Repo: '.$repoName);
            $cmd = ['helm', 'repo', 'add', $repoName, $deploy->chartRemote->repoUrl];
            $process = new Process($cmd);
            $process->setTty($this->config->getTtyEnabled());
            $process->run();

            CurCom::get()->info('Running helm update...');
            $cmd = ['helm', 'repo', 'update', $repoName];
            $process = new Process($cmd);
            $process->setTty($this->config->getTtyEnabled());
            $process->run();


            CurCom::get()->info('Installing '.$installableEntityName.' from '.$deploy->chartRemote->repoUrl.' version '.$deploy->chartRemote->version.' into namespace '.$deploy->namespace);
            $cmd = ['helm', 'upgrade'];
            if ($this->config->getIfWeNeedToWaitHelms()) {
                $cmd = [...$cmd, '--wait'];
            }
            if ($this->config->getNeedCreateRancherNamespace()) {
                echo 'We are trying to create namespace '.$deploy->namespace.' in rancher...';
                $response = Http::withHeader('Authorization', 'Bearer '.$this->config->getRancherToken())
                    ->post($this->config->getRancherFullUrl().'/v3/cluster/'.$this->config->getRancherClusterId().'/namespaces', [
                        'name' => $deploy->namespace,
                        'projectId' => $this->config->getRancherClusterId().':'.$this->config->getRancherProjectId()
                    ]);
                if ($response->status() !== 201 && $response->status() !== 409) {
                    echo $response->body();
                    $response->throw();
                }

                $cmd = [...$cmd, '-n', $deploy->namespace, '--version', $deploy->chartRemote->version, '--install', $installableEntityName.'-'.$deploy->appEnvironment];
            } else {
                $cmd = [...$cmd, '--create-namespace', '-n', $deploy->namespace, '--version', $deploy->chartRemote->version, '--install', $installableEntityName.'-'.$deploy->appEnvironment];
            }

            foreach (explode(' ', $values) as $word) {
                $cmd[] = $word;
            }
            $cmd[] = $repoName.'/'.$deploy->chartRemote->chartName;
            $process = new Process($cmd);
            $process->setTty($this->config->getTtyEnabled());
            $process->run();
            if (!$this->config->getTtyEnabled()) {
                if ($process->isSuccessful()) {
                    echo $process->getOutput()."\n";
                } else {
                    echo $process->getErrorOutput()."\n";
                    exit(1);
                }
            }

        } else {

            $values = $this->valuesCmdBuilding($deploy, $workingDirectory);
            $cmd = ['helm', 'upgrade'];
            if ($this->config->getIfWeNeedToWaitHelms()) {
                $cmd = [...$cmd, '--wait'];
            }
            CurCom::get()->info('Installing '.$installableEntityName.' from "'.$deploy->chartLocal->chartPath.'" into namespace '.$deploy->namespace);
            if ($this->config->getNeedCreateRancherNamespace()) {
                $response = Http::withHeader('Authorization', 'Bearer '.$this->config->getRancherToken())
                    ->post($this->config->getRancherFullUrl().'/v3/cluster/'.$this->config->getRancherClusterId().'/namespaces', [
                        'name' => $deploy->namespace,
                        'projectId' => $this->config->getRancherClusterId().':'.$this->config->getRancherProjectId()
                    ]);
                if ($response->status() !== 201 && $response->status() !== 409) {
                    echo $response->body();
                    $response->throw();
                }
                $cmd = [...$cmd, '-n', $deploy->namespace, '--install', $installableEntityName.'-'.$deploy->appEnvironment];
            } else {
                $cmd = [...$cmd, '--create-namespace', '-n', $deploy->namespace, '--install', $installableEntityName.'-'.$deploy->appEnvironment];
            }

            foreach (explode(' ', $values) as $word) {
                $cmd[] = $word;
            }
            $cmd[] = $deploy->chartLocal->chartPath;
            $process = new Process($cmd);
            $process->setTty($this->config->getTtyEnabled());
            $process->run();
            if (!$this->config->getTtyEnabled()) {
                if ($process->isSuccessful()) {
                    echo $process->getOutput()."\n";
                } else {
                    echo $process->getErrorOutput()."\n";
                    exit(1);
                }
            }
        }
    }

    public function render(Deploy $deploy, string $installableEntityName, string $workingDirectory) {

        $this->combineValues($deploy);

        if ($deploy->chartLocal === null) {
            $repoName = $this->config->getProjectName().'-'.$installableEntityName;
            CurCom::get()->info('Working with remote repo: '.$repoName);

            $values = $this->valuesCmdBuilding($deploy, $workingDirectory);

            CurCom::get()->info('Removing old repo: '.$repoName);
            $cmd = ['helm', 'repo', 'remove', $repoName, $deploy->chartRemote->repoUrl];
            $process = new Process($cmd);
            $process->run();


            CurCom::get()->info('Adding Repo: '.$repoName);
            $cmd = ['helm', 'repo', 'add', $repoName, $deploy->chartRemote->repoUrl];
            $process = new Process($cmd);
            $process->setTty($this->config->getTtyEnabled());
            $process->run();

            CurCom::get()->info('Running helm update...');
            $cmd = ['helm', 'repo', 'update', $repoName];
            $process = new Process($cmd);
            $process->setTty($this->config->getTtyEnabled());
            $process->run();

            CurCom::get()->info('Rendering '.$installableEntityName.' from '.$deploy->chartRemote->repoUrl.' version '.$deploy->chartRemote->version.' into namespace '.$deploy->namespace);
            $cmd = ['helm', 'template', '-n', $deploy->namespace, '--version', $deploy->chartRemote->version, $installableEntityName.'-'.$deploy->appEnvironment];

            foreach (explode(' ', $values) as $word) {
                $cmd[] = $word;
            }
            $cmd[] = $repoName.'/'.$installableEntityName;
            $process = new Process($cmd);
            $process->setTty($this->config->getTtyEnabled());
            $process->run();
            if (!$this->config->getTtyEnabled()) {
                if ($process->isSuccessful()) {
                    echo $process->getOutput()."\n";
                } else {
                    echo $process->getErrorOutput()."\n";
                    exit(1);
                }
            }
        } else {
            $values = $this->valuesCmdBuilding($deploy, $workingDirectory);

            CurCom::get()->info('Rendering '.$installableEntityName.' from "'.$deploy->chartLocal->chartPath.'" into namespace '.$deploy->namespace);
            $cmd = ['helm', 'template', '-n', $deploy->namespace, $installableEntityName.'-'.$deploy->appEnvironment, $deploy->chartLocal->chartPath];
            foreach (explode(' ', $values) as $word) {
                $cmd[] = $word;
            }
            $process = new Process($cmd);
            $process->setTty($this->config->getTtyEnabled());
            $process->run();
            if (!$this->config->getTtyEnabled()) {
                if ($process->isSuccessful()) {
                    echo $process->getOutput()."\n";
                } else {
                    echo $process->getErrorOutput()."\n";
                    exit(1);
                }
            }
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
        file_put_contents($filename, Yaml::dump($parsed, 8,2));

    }

    public function combineValues(Deploy $deploy)
    {
        @mkdir(dirname($deploy->finalValuesFilePath), 0777, true);
        touch(dirname($deploy->finalValuesFilePath).'/.gitignore');
        $gitignore = file_get_contents(dirname($deploy->finalValuesFilePath).'/.gitignore');
        if (!$deploy->combineFinalValuesFromEnvAndOverrides) {
            $gitignore = str_replace(basename($deploy->finalValuesFilePath), '', $gitignore);
            $gitignore = str_replace("\n\n", "\n", $gitignore);
            file_put_contents(dirname($deploy->finalValuesFilePath).'/.gitignore', $gitignore);
            return;
        } else {
            $found = false;
            $explodedIgnore = explode("\n", $gitignore);
            foreach ($explodedIgnore as $line) {
                if (trim($line) === basename($deploy->finalValuesFilePath)) {
                    $found = true;
                }
            }
            if (!$found) {
                $gitignore .= "\n" . basename($deploy->finalValuesFilePath);
                file_put_contents(dirname($deploy->finalValuesFilePath) . '/.gitignore', $gitignore);
            }
        };
        $valuesByEnvDirectoryPath = $deploy->valuesByEnv;
        $currentEnv = $deploy->appEnvironment;
        @mkdir($valuesByEnvDirectoryPath, 0777, true);
        if (file_exists($valuesByEnvDirectoryPath . '/common.values.yaml')) {
            $commonParsed = Yaml::parseFile($valuesByEnvDirectoryPath . '/common.values.yaml', YAML::PARSE_OBJECT);
            if (!$commonParsed) {
                $commonParsed = [];
            }
        } else {
            touch ($valuesByEnvDirectoryPath . '/common.values.yaml');
            $commonParsed = [];
        }


        if (file_exists($valuesByEnvDirectoryPath . '/' . $currentEnv . '.values.yaml')) {
            $valuesParsed = Yaml::parseFile($valuesByEnvDirectoryPath . '/' . $currentEnv . '.values.yaml', YAML::PARSE_OBJECT);
            if (!$valuesParsed) {
                $valuesParsed = [];
            }
        } else {
            $valuesParsed = [];
        }

        if (file_exists($valuesByEnvDirectoryPath . '/secrets.values.yaml')) {
            $secretsParsed = Yaml::parseFile($valuesByEnvDirectoryPath . '/secrets.values.yaml', YAML::PARSE_OBJECT);
            if (!$secretsParsed) {
                $secretsParsed = [];
            }
        } else {
            $secretsParsed = [];
        }

        if (file_exists($valuesByEnvDirectoryPath . '/overrides.values.yaml')) {
            $overridesParsed = Yaml::parseFile($valuesByEnvDirectoryPath . '/overrides.values.yaml', YAML::PARSE_OBJECT);
            if (!$overridesParsed) {
                $overridesParsed = [];
            }
        } else {
            $overridesParsed = [];
        }

        $values = array_replace_recursive(
            $commonParsed,
            $valuesParsed,
            $secretsParsed,
            $overridesParsed
        );
        $dump = Yaml::dump($values, 8, 2);
        @mkdir(dirname($deploy->finalValuesFilePath), 0777, true);
        file_put_contents($deploy->finalValuesFilePath, $dump);


    }
}
