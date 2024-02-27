<?php

namespace App\Modules\ProjectConfig;

use App\Modules\ConfigObjects\ChartLocal;
use App\Modules\ConfigObjects\ChartRemote;
use App\Modules\ConfigObjects\Command;
use App\Modules\ConfigObjects\CopyPath;
use App\Modules\ConfigObjects\Deploy;
use App\Modules\ConfigObjects\DevelopmentPackage;
use App\Modules\ConfigObjects\MovePath;
use App\Modules\ConfigObjects\SynchronizedTool;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Config
{
    private array $config;
    private string $projectName;
    /**
     * @var mixed|string
     */
    private string $helmChartRepository;
    /**
     * @var mixed|string
     */
    private string $devImage;

    public function getDevWorkingDir()
    {
        return $this->config['developerWorkingDirectory'] ?? '/';
    }

    private function readEnvVariables() {
        $variables = [
            'NAMESPACE',
            'APP_ENV',
            'CHART_VERSION',
            'APP_VERSION',
            'DEVELOPER_NAME',
        ];
        foreach ($variables as $variable) {
            if (getenv($variable)) {
                echo "Using $variable from environment: ".getenv($variable)."\n";
            }
        }
    }

    public function getDeveloperName() {
        return getenv('DEVELOPER_NAME') ?: $this->config['developerName'];
    }

    public function getAppEnv() {
        if (!isset($this->config['deploy']['appEnvironment'])) throw new \Exception('You should set up "deploy" block in frock.yaml');
        return getenv('APP_ENV') ?: $this->config['deploy']['appEnvironment'];
    }

    public function __construct()
    {
        $this->config = array_merge($this->readConfig(), $this->readOverridenConfig());

        $this->projectName = $this->config['projectName'];
        $this->helmChartRepository = $this->config['helmChartRepository'] ?? 'https://github.com/frockdev/app-chart.git';
        $this->devImage = $this->config['devImage'] ?? 'vladitot/php83-swow-local:master';
    }

    private function readConfig(): array {
        if (!file_exists(getcwd().'/frock.yaml')) {
            $dirname = basename(getcwd());
            file_put_contents(getcwd().'/frock.yaml', "infraVersion: main\nprojectName: ".$dirname."\n");
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

    public function getDevelopmentPackages(): array {
        $packages = $this->config['developmentPackages'] ?? [];
        $result = [];
        foreach ($packages as $package) {
            $result[] = new DevelopmentPackage($package['sshLink'], $package['devName'], $package['branchOrTag']);
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
            return (array)$data;
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

    public function getHelmChartRepository(): string
    {
        return $this->helmChartRepository;
    }

    public function getDeployConfig(): Deploy {
        if (isset($this->config['deploy']['chartRemote']) && $this->config['deploy']['chartRemote']) {
            return new Deploy(
                namespace: $this->getNamespace(),
                appEnvironment: $this->getAppEnv(),
                kubectlExecCommand: $this->getKubectlExecCommand(),
                kubectlDebugCommand: $this->getKubectlDebugCommand(),
                chartLocal: null,
                chartRemote: new ChartRemote(
                    $this->config['deploy']['chartRemote']['repoUrl'],
                    $this->config['deploy']['chartRemote']['version'],
                    $this->config['deploy']['chartRemote']['chartName']
                ),
                valuesByEnv: $this->config['deploy']['valuesByEnv'] ?? $this->getWorkingDir().'/values'
            );
        } else {
            return new Deploy(
                namespace: $this->getNamespace(),
                appEnvironment: $this->getAppEnv(),
                kubectlExecCommand: $this->getKubectlExecCommand(),
                kubectlDebugCommand: $this->getKubectlDebugCommand(),
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
        return getenv('CHART_VERSION') ?: $this->config['deploy']['chartLocal']['chartVersion'] ?? null;
    }

    public function getAppVersion() {
        return getenv('APP_VERSION') ?: $this->config['deploy']['chartLocal']['version'] ?? null;
    }

    public function getDevImage(): string
    {
        return $this->devImage;
    }

    public function getKubectlExecCommand()
    {
        return $this->config['deploy']['kubectlExecCommand'] ?? 'kubectl exec -n %s -it %s -- bash -c';
    }

    public function getDebugEnvExportForCommands() {
        $arr = $this->config['debugEnvForCommands'] ?? [];
        $exportString = '';
        foreach ($arr as $key => $value) {
            $exportString .= 'export '.$key.'='.$value.' && ';
        }
        return substr($exportString, 0, -4);
    }

    public function getNamespace()
    {
        return getenv('NAMESPACE') ?: ($this->config['deploy']['namespace'] ?? $this->getProjectName().'-ns');
    }
}
