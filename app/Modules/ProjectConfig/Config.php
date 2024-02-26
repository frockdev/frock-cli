<?php

namespace App\Modules\ProjectConfig;

use App\Modules\ConfigObjects\Command;
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
            $result[] = new SynchronizedTool(
                link: $tool['link'],
                name: $tool['name'],
                version: $tool['version'],
                excludePaths: $tool['excludePaths'] ?? [],
                movePaths: $movePaths,
                gitignore: $tool['gitignore'] ?? []
            );
        }
        return $result;
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

    public function getDevImage(): string
    {
        return $this->devImage;
    }
}
