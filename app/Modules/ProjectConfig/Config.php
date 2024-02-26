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

    private function readEnvVariables() {
        $variables = [
            'NAMESPACE',
            'APP_ENV',
            'CHART_VERSION',
            'APP_VERSION'
        ];
        foreach ($variables as $variable) {
            if (getenv($variable)) {
                echo "Using $variable from environment: ".getenv($variable)."\n";
            }
        }
    }

    public function getAppEnv() {
        return getenv('APP_ENV') ?? $this->config['deploy']['appEnvironment'];
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
                description: $command['description']);
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
        if ($this->config['deploy']['chartRemote']) {
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
                ),
                chartRemote: null,
                valuesByEnv: $this->config['deploy']['valuesByEnv'] ?? $this->getWorkingDir().'/values'
            );
        }
    }

    public function getChartVersion() {
        return getenv('CHART_VERSION') ?? null;
    }

    public function getAppVersion() {
        return getenv('APP_VERSION') ?? null;
    }

    public function getDevImage(): string
    {
        return $this->devImage;
    }

    public function getKubectlExecCommand()
    {
        return $this->config['deploy']['kubectlExecCommand'] ?? 'kubectl exec -n '.$this->getNamespace().' -it $(kubectl get pods -l kubectlExec=true -o jsonpath="{.items[0].metadata.name}") -- /bin/bash -c "cd /var/www/php && ';
    }

    public function getKubectlDebugCommand()
    {
        return $this->config['deploy']['kubectlDebugCommand'] ?? 'kubectl exec -n '.$this->getNamespace().' -it $(kubectl get pods -l kubectlExec=true -o jsonpath="{.items[0].metadata.name}") -- /bin/bash -c "cd /var/www/php && export XDEBUG_MODE=debug && export XDEBUG_SESSION=PHPSTORM && ';
    }

    public function getNamespace()
    {
        return getenv('NAMESPACE') ?? $this->config['deploy']['namespace'] ?? $this->getProjectName().'-ns';
    }
}
