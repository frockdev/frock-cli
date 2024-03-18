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

    const RANCHER_PROJECT_ID = 'RANCHER_PROJECT_ID';
    const RANCHER_CLUSTER_ID = 'RANCHER_CLUSTER_ID';
    const RANCHER_URL = 'RANCHER_URL';

    const RANCHER_TOKEN = 'RANCHER_TOKEN';
    const TTY_DISABLED = 'TTY_DISABLED';

    const RANCHER_NEED_CREATE_NAMESPACE = 'RANCHER_NEED_CREATE_NAMESPACE';

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

    public function getNeedCreateRancherNamespace(): ?string {
        return (bool)getenv('RANCHER_NEED_CREATE_NAMESPACE');
    }

    public function getTtyEnabled() {
        if (getenv('TTY_DISABLED')) {
            return false;
        } else {
            return true;
        }
    }

    private function notifyAboutReadVariables() {
        $variables = [
            self::NAMESPACE,
            self::APP_ENV,
            self::CHART_VERSION,
            self::APP_VERSION,
            self::DEVELOPER_NAME,
            self::RANCHER_PROJECT_ID,
            self::RANCHER_CLUSTER_ID,
            self::RANCHER_URL,
            self::RANCHER_NEED_CREATE_NAMESPACE,
            self::RANCHER_TOKEN,
            self::TTY_DISABLED,
        ];
        foreach ($variables as $variable) {
            if (in_array($variable, [self::TTY_DISABLED])) {
                if (getenv($variable)) {
                    echo "Loading $variable from env variable ".getenv($variable)."..\n";
                }
            } else {
                if (getenv($variable)) {
                    echo "Loading $variable from env variable..\n";
                }
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
        $this->notifyAboutReadVariables();
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

    public function getCurrentVersionOfTool(string $toolName) {
        $yaml = file(getcwd().'/frock.yaml');
        foreach ($yaml as &$line) {
            $line = rtrim($line);
        }
        unset ($line);

        $currentBlock = '';
        $toolsAndVersions = [];
        foreach ($yaml as $key=>$line) {
            if (strpos($line, 'synchronizedTools:')!==false) {
                $currentBlock = 'synchronizedTools';
            }
            if ($currentBlock!='synchronizedTools') {
                continue;
            }
            if (strpos($line, 'name:')!==false) {
                $toolsAndVersions[$key] = $line;
                continue;
            }
            if (strpos($line, 'version:')!==false) {
                $toolsAndVersions[$key] = $line;
                continue;
            }
        }

        $j=0;
        $currentToolLine = '';
        $currentVersionLine = '';
        $currentVersionLineNumber = -1;
        foreach ($toolsAndVersions as $key=>$line) {
            if ($j>1) {
                $j=0;
                if ($currentVersionLine && $currentToolLine) {
                    if (preg_match('/name:\s*[\'"]*'.$toolName.'[\'"]*/', $currentToolLine)) {
                        $matches = [];
                        preg_match('/v\d+\.\d+\.\d+/', $currentVersionLine, $matches);
                        return $matches[0];
                    }
                }
                $currentToolLine = '';
                $currentVersionLine = '';
                $currentVersionLineNumber = -1;
            }
            if (str_contains($line, 'name:')) {
                $currentToolLine = $line;
                $j++;
                continue;
            }
            if (str_contains($line, 'version:')) {
                $currentVersionLineNumber = $key;
                $currentVersionLine = $line;
                $j++;
                continue;
            }
        }
        throw new \Exception('Tool not found in frock.yaml');
    }

    public function setNewSynchronizedToolsetVersion(string $toolName, string $newVersion): void {

        $yaml = file(getcwd().'/frock.yaml');
        foreach ($yaml as &$line) {
            $line = rtrim($line);
        }
        unset ($line);

        $currentBlock = '';
        $toolsAndVersions = [];
        foreach ($yaml as $key=>$line) {
            if (strpos($line, 'synchronizedTools:')!==false) {
                $currentBlock = 'synchronizedTools';
            }
            if ($currentBlock!='synchronizedTools') {
                continue;
            }
            if (strpos($line, 'name:')!==false) {
                $toolsAndVersions[$key] = $line;
                continue;
            }
            if (strpos($line, 'version:')!==false) {
                $toolsAndVersions[$key] = $line;
                continue;
            }
        }

        $j=0;
        $currentTool = '';
        $currentVersion = '';
        $currentVersionLineNumber = -1;
        foreach ($toolsAndVersions as $key=>$line) {
            if ($j>1) {
                $j=0;
                if ($currentVersion && $currentTool) {
                    if (preg_match('/name:\s*[\'"]*'.$toolName.'[\'"]*/', $currentTool)) {
                        $yaml[$currentVersionLineNumber] = '    version: '.$newVersion;
                    }
                }
                $currentTool = '';
                $currentVersion = '';
                $currentVersionLineNumber = -1;
            }
            if (str_contains($line, 'name:')) {
                $currentTool = $line;
                $j++;
                continue;
            }
            if (str_contains($line, 'version:')) {
                $currentVersionLineNumber = $key;
                $currentVersion = $line;
                $j++;
                continue;
            }
        }
        file_put_contents(getcwd().'/frock.yaml', implode("\n", $yaml));

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

    public function ifBoxShouldBeAutoDeployed(string $boxName): bool {
        if (isset($this->config['boxes'][$boxName]['autoDeployTo'])) {
            return in_array($this->getAppEnv(), $this->config['boxes'][$boxName]['autoDeployTo']);
        }
        return false;
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
                $finalValuesFilePath = ($deploy['valuesByEnvDirectory'] ?? 'boxes/'.$boxName.'/values').'/values.yaml';
            }
            if ($deploy['sameNamespace']??false) {
                $namespace = $this->getNamespace();
            } else {
                $namespace = $this->getNamespace().'-'.$boxName;
            }
            if (isset($deploy['chartRemote']) && $deploy['chartRemote']) {
                $boxes[$boxName] = new Deploy(
                    namespace: $namespace,
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
                    valuesByEnv: $deploy['valuesByEnvDirectory'] ?? $this->getWorkingDir().'boxes/'.$boxName.'/values',
                );
            } else {
                $boxes[$boxName] = new Deploy(
                    namespace: $namespace,
                    appEnvironment: $this->getAppEnv(),
                    finalValuesFilePath: $finalValuesFilePath,
                    combineFinalValuesFromEnvAndOverrides: $deploy['combineFinalValuesFromEnvAndOverrides'] ?? true,
                    redefineValuesFromEnvVariables: $deploy['redefineValuesFromEnvVariables'] ?? [],
                    redefineValuesFromYamlFiles: $redefines,
                    chartLocal: new ChartLocal(
                        chartPath: $this->getWorkingDir().'/'.$deploy['chartLocal']['chartPath'],
                        appVersion: $this->getAppVersion(),
                        chartVersion: $this->getChartVersion(),
                        valuesKeyOfLocalDirectory: $deploy['chartLocal']['valuesKeyOfLocalDirectory']??null,
                        localDirectoryPrefix: $deploy['chartLocal']['localDirectoryPrefix']??''
                    ),
                    chartRemote: null,
                    valuesByEnv: $deploy['valuesByEnvDirectory'] ?? $this->getWorkingDir().'boxes/'.$boxName.'/values'
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
            $finalValuesFilePath = ($this->config['deploy']['valuesByEnvDirectory'] ?? 'values').'/values.yaml';
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
                valuesByEnv: $this->config['deploy']['valuesByEnvDirectory'] ?? $this->getWorkingDir().'/values'
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
                    valuesKeyOfLocalDirectory: $this->config['deploy']['chartLocal']['valuesKeyOfLocalDirectory']??null,
                    localDirectoryPrefix: $this->config['deploy']['chartLocal']['localDirectoryPrefix']??''
                ),
                chartRemote: null,
                valuesByEnv: $this->config['deploy']['valuesByEnvDirectory'] ?? $this->getWorkingDir().'/values'
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

    public function getRancherFullUrl()
    {
        return getenv('RANCHER_URL') ?? throw new \Exception('RANCHER_URL is not set');
    }

    public function getRancherClusterId()
    {
        return getenv('RANCHER_CLUSTER_ID') ?? throw new \Exception('RANCHER_CLUSTER_ID is not set');
    }

    public function getRancherProjectId()
    {
        return getenv('RANCHER_PROJECT_ID') ?? throw new \Exception('RANCHER_PROJECT_ID is not set');
    }

    public function getRancherToken()
    {
        return getenv('RANCHER_TOKEN') ?? throw new \Exception('RANCHER_TOKEN is not set');
    }

    public function getIfWeNeedToWaitHelms()
    {
        return (bool)(getenv('WAIT_HELM_DEPLOY') ?? false);
    }
}
