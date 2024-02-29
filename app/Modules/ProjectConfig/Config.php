<?php

namespace App\Modules\ProjectConfig;

use App\Modules\ConfigObjects\ChartLocal;
use App\Modules\ConfigObjects\ChartRemote;
use App\Modules\ConfigObjects\Command;
use App\Modules\ConfigObjects\CopyPath;
use App\Modules\ConfigObjects\Deploy;
use App\Modules\ConfigObjects\DevelopmentPackage;
use App\Modules\ConfigObjects\MovePath;
use App\Modules\ConfigObjects\RedefineValuesFromYamlFiles;
use App\Modules\ConfigObjects\SynchronizedTool;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Config
{
    const NAMESPACE = 'NAMESPACE';
    const APP_ENV = 'APP_ENV';
    const CHART_VERSION = 'CHART_VERSION';
    const APP_VERSION = 'APP_VERSION';
    const DEVELOPER_NAME = 'DEVELOPER_NAME';
    private array $config;
    private string $projectName;

    public function getDevWorkingDir()
    {
        if (!isset($this->config['settings']['developerWorkingDirectory'])) {
            return '/';
        } else {
            return $this->config['settings']['developerWorkingDirectory'];
        }

    }

    private function readEnvVariables() {
        $variables = [
            self::NAMESPACE,
            self::APP_ENV,
            self::CHART_VERSION,
            self::APP_VERSION,
            self::DEVELOPER_NAME,
        ];
        foreach ($variables as $variable) {
            if (getenv($variable)) {
                echo "Using $variable from environment: ".getenv($variable)."\n";
            }
        }
    }

    public function getDeveloperName() {
        if (getenv(self::DEVELOPER_NAME)) {
            return getenv(self::DEVELOPER_NAME);
        } else {
            if (isset($this->config['settings']['developerName'])) {
                return $this->config['settings']['developerName'];
            } else {
                return 'FrockDeveloper';
            }
        }
    }

    public function getDevContainerLabels() {
        return $this->config['settings']['devcontainerLabels'] ?? [];
    }

    public function getAppEnv() {
        if (!isset($this->config['deploy'])) throw new \Exception('You should set up "deploy" block in frock.yaml');
        return getenv(self::APP_ENV) ?getenv(self::APP_ENV): $this->config['deploy']['appEnvironment'];
    }

    public function __construct()
    {
        $this->config = array_merge($this->readConfig(), $this->readOverridenConfig());
        $this->projectName = $this->config['projectName'];

    }

    private function readConfig(): array {
        if (!file_exists(getcwd().'/frock.yaml')) {
            $dirname = basename(getcwd());
            file_put_contents(getcwd().'/frock.yaml', "projectName: ".$dirname."\n");
        }
        $parsed = Yaml::parseFile(getcwd().'/frock.yaml');
        return $parsed;
    }

    /**
     * @return Command[]
     * @throws \Exception
     */
    public function getCommands(): array {
        $commands = $this->config['commands'] ?? [];
        $result = [];
        foreach ($commands as $command) {
            $result[] = new Command(
                signature: $command['signature'],
                type: $command['type'],
                command: $command['command'],
                description: $command['description'],
                debug: $command['debug'] ?? false
            );
        }
        return $result;
    }

    /**
     * @return array|DevelopmentPackage[]
     */
    public function getDevelopmentPackages(): array {
        $packages = $this->config['developmentPackages'] ?? [];
        $result = [];
        foreach ($packages as $package) {
            $result[$package['shortName']] = new DevelopmentPackage(
                sshLink: $package['sshLink'],
                branch: $package['branch'],
                composerPackageName: $package['composerPackageName'],
                shortName: $package['shortName'],
                pushWhenSwitchingOff: $package['pushWhenSwitchingOff']
            );
        }
        return $result;
    }


    /**
     * @return array|SynchronizedTool[]
     */
    public function getSynchronizedTools(): array {
        $tools = $this->config['synchronizedTools'] ?? [];
        $result = [];
        foreach ($tools as $tool) {
            $movePaths = [];
            foreach ($tool['movePaths'] ?? [] as $movePath) {
                $movePaths[] = new MovePath($movePath['from'], $movePath['to']);
            }
            $copyPaths=[];
            foreach ($tool['copyPaths'] ?? [] as $copyPath) {
                $copyPaths[] = new CopyPath($copyPath['from'], $copyPath['to']);
            }
            $result[$tool['name']] = new SynchronizedTool(
                link: $tool['link'],
                name: $tool['name'],
                version: $tool['version'],
                excludePaths: $tool['excludePaths'] ?? [],
                movePaths: $movePaths,
                gitignore: $tool['gitignore'] ?? [],
                copyPaths: $copyPaths,
                onlyPaths: $tool['onlyPaths'] ?? []
            );
        }
        return $result;
    }

    public function setNewSynchronizedToolsetVersion(string $toolName, string $newVersion): void {

        foreach ($this->config['synchronizedTools'] as &$tool) {
            if ($tool['name'] === $toolName) {
                $tool['version'] = $newVersion;
                $yaml = Yaml::dump($this->config, 8, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                file_put_contents(getcwd().'/frock.yaml', $yaml);
                return;
            }
        }

    }

    private function readOverridenConfig()
    {
        if (file_exists(getcwd().'/frock.overriden.yaml')) {
            $data = Yaml::parseFile(getcwd().'/frock.overriden.yaml');
            return $data;
        }
        return [];
    }

    public function getProjectName(): string
    {
        if ($this->projectName) {
            return $this->projectName;
        } else {
            return basename(getcwd());
        }
    }

    public function getWorkingDir() {
        return getcwd();
    }

    public function getCurrentUserId() {
        return trim(shell_exec('id -u'));
    }

    public function getCurrentUserName() {
        return trim(shell_exec('whoami'));
    }

    /**
     * @return array|Deploy[]
     * @throws \Exception
     */
    public function getBoxes(): array {
        if (!isset($this->config['boxes'])) {
            return [];
        }
        $boxes = [];
        foreach ($this->config['boxes'] as $boxName=>$deploy) {
            $redefines = [];
            if (isset($deploy['redefineValuesFromYamlFiles']) && is_array($deploy['redefineValuesFromYamlFiles'])) {
                foreach ($deploy['redefineValuesFromYamlFiles'] as $file=>$arr) {
                    $redefines[] = new RedefineValuesFromYamlFiles($file, $arr);
                }
            }

            if (isset($deploy['finalValuesFilePath']) && $deploy['finalValuesFilePath']) {
                $finalValuesFilePath = $deploy['finalValuesFilePath'];
            } else {
                $finalValuesFilePath = ($deploy['valuesByEnv'] ?? 'boxes/'.$boxName.'/values').'/values.yaml';
            }

            if (isset($deploy['chartRemote']) && $deploy['chartRemote']) {
                $boxes[$boxName] = new Deploy(
                    namespace: $this->getNamespace().'-'.$boxName,
                    appEnvironment: $this->getAppEnv(),
                    finalValuesFilePath: $finalValuesFilePath,
                    combineFinalValuesFromEnvAndOverrides: $deploy['combineFinalValuesFromEnvAndOverrides'] ?? true,
                    redefineValuesFromEnvVariables: $deploy['redefineValuesFromEnvVariables'] ?? [],
                    redefineValuesFromYamlFiles: $redefines,
                    chartLocal: null,
                    chartRemote: new ChartRemote(
                        $deploy['chartRemote']['chartUrl'],
                        $deploy['chartRemote']['version'],
                        $deploy['chartRemote']['chartName']
                    ),
                    valuesByEnv: $deploy['valuesByEnv'] ?? $this->getWorkingDir().'/values'
                );
            } else {
                $boxes[$boxName] = new Deploy(
                    namespace: $this->getNamespace().'-'.$boxName,
                    appEnvironment: $this->getAppEnv(),
                    finalValuesFilePath: $finalValuesFilePath,
                    combineFinalValuesFromEnvAndOverrides: $deploy['combineFinalValuesFromEnvAndOverrides'] ?? true,
                    redefineValuesFromEnvVariables: $deploy['redefineValuesFromEnvVariables'] ?? [],
                    redefineValuesFromYamlFiles: $redefines,
                    chartLocal: new ChartLocal(
                        chartPath: $this->getWorkingDir().'/'.$deploy['chartLocal']['chartPath'],
                        appVersion: $this->getAppVersion(),
                        chartVersion: $this->getChartVersion(),
                        valuesKeyOfLocalDirectory: $deploy['chartLocal']['valuesKeyOfLocalDirectory']??null
                    ),
                    chartRemote: null,
                    valuesByEnv: $deploy['valuesByEnv'] ?? $this->getWorkingDir().'/values'
                );
            }
        }
        return $boxes;
    }

    public function getDeployConfig(): Deploy {

        $redefines = [];
        if (isset($this->config['deploy']['redefineValuesFromYamlFiles']) && is_array($this->config['deploy']['redefineValuesFromYamlFiles'])) {
            foreach ($this->config['deploy']['redefineValuesFromYamlFiles'] as $file=>$arr) {
                $redefines[] = new RedefineValuesFromYamlFiles($file, $arr);
            }
        }

        if (isset($this->config['deploy']['finalValuesFilePath']) && $this->config['deploy']['finalValuesFilePath']) {
            $finalValuesFilePath = $this->config['deploy']['finalValuesFilePath'];
        } else {
            $finalValuesFilePath = ($this->config['deploy']['valuesByEnv'] ?? 'values').'/values.yaml';
        }

        if (isset($this->config['deploy']['chartRemote']) && $this->config['deploy']['chartRemote']) {
            return new Deploy(
                namespace: $this->getNamespace(),
                appEnvironment: $this->getAppEnv(),
                finalValuesFilePath: $finalValuesFilePath,
                combineFinalValuesFromEnvAndOverrides: $this->config['deploy']['combineFinalValuesFromEnvAndOverrides'] ?? true,
                redefineValuesFromEnvVariables: $this->config['deploy']['redefineValuesFromEnvVariables'] ?? [],
                redefineValuesFromYamlFiles: $redefines,
                chartLocal: null,
                chartRemote: new ChartRemote(
                    $this->config['deploy']['chartRemote']['chartUrl'],
                    $this->config['deploy']['chartRemote']['version'],
                    $this->config['deploy']['chartRemote']['chartName']
                ),
                valuesByEnv: $this->config['deploy']['valuesByEnv'] ?? $this->getWorkingDir().'/values'
            );
        } else {
            return new Deploy(
                namespace: $this->getNamespace(),
                appEnvironment: $this->getAppEnv(),
                finalValuesFilePath: $finalValuesFilePath,
                combineFinalValuesFromEnvAndOverrides: $this->config['deploy']['combineFinalValuesFromEnvAndOverrides'] ?? true,
                redefineValuesFromEnvVariables: $this->config['deploy']['redefineValuesFromEnvVariables'] ?? [],
                redefineValuesFromYamlFiles: $redefines,
                chartLocal: new ChartLocal(
                    chartPath: $this->getWorkingDir().'/'.$this->config['deploy']['chartLocal']['chartPath'],
                    appVersion: $this->getAppVersion(),
                    chartVersion: $this->getChartVersion(),
                    valuesKeyOfLocalDirectory: $this->config['deploy']['chartLocal']['valuesKeyOfLocalDirectory']??null
                ),
                chartRemote: null,
                valuesByEnv: $this->config['deploy']['valuesByEnv'] ?? $this->getWorkingDir().'/values'
            );
        }
    }

    public function getChartVersion() {
        return getenv(self::CHART_VERSION) ?: $this->config['deploy']['chartLocal']['chartVersion'] ?? null;
    }

    public function getAppVersion() {
        return getenv(self::APP_VERSION) ?: $this->config['deploy']['chartLocal']['version'] ?? null;
    }

    public function getDevContainerShell() {
        if (isset($this->config['settings']['devContainerShell'])) {
            return $this->config['settings']['devContainerShell'];
        } else {
            return 'bash';
        }
    }

    public function getKubectlExecCommand(string $namespace, string $podname)
    {
        return ['kubectl', 'exec', '-n', $namespace, '-it', $podname, '--' , $this->getDevContainerShell(),'-c'];
    }

    public function getDebugEnvExportForCommands():array {
        if (!isset($this->config['settings']['debugEnvForCommands'])) {
            $arr = [];
        } else {
            $arr = $this->config['settings']['debugEnvForCommands'];
        }
        $exportArr = [];
        foreach ($arr as $key => $value) {
            $exportArr[] = 'export';
            $exportArr[] = $key.'='.$value;
            $exportArr[] = '&&';
        }
        if (count($exportArr)>0) {
            unset($exportArr[count($exportArr)-1]);
        }
        return $exportArr;
    }

    public function getNamespace()
    {
        return getenv(self::NAMESPACE) ?: ($this->config['deploy']['namespace'] ?? $this->getProjectName().'-'.$this->getAppEnv());
    }
}
