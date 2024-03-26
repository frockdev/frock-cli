<?php

namespace App\Providers;

use App\Modules\Kubernetes\KubernetesClusterManager;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Process\Process;

class AppServiceProvider extends ServiceProvider
{

    public function registerCustomCommands()
    {
        /** @var Config $config */
        $config = $this->app->make(Config::class);
        $clusterManager = $this->app->make(\App\Modules\Kubernetes\KubernetesClusterManager::class);
        $resultCommands = [];
        /** @var \App\Modules\ConfigObjects\Command $command */
        foreach ($config->getCommands() as $command) {

            $resultCommands[] = new class($command, $config->getNamespace(), $clusterManager) extends Command {
                private \App\Modules\ConfigObjects\Command $command;

                private string $namespace;
                private KubernetesClusterManager $clusterManager;

                public function __construct(\App\Modules\ConfigObjects\Command $command, string $namespace, KubernetesClusterManager $clusterManager)
                {
                    $this->signature = $command->signature;
                    $this->description = $command->description;

                    parent::__construct();
                    $this->command = $command;
                    $this->namespace = $namespace;
                    $this->clusterManager = $clusterManager;
                }

                public function handle(Config $config) {

                    if (!in_array($this->command->type, [
                            \App\Modules\ConfigObjects\Command::ARTISAN_TYPE,
                            \App\Modules\ConfigObjects\Command::FROCK_TYPE,
                            \App\Modules\ConfigObjects\Command::COMPOSER_TYPE,
                            \App\Modules\ConfigObjects\Command::RAW_TYPE,
                            \App\Modules\ConfigObjects\Command::BASH_TYPE,
                            \App\Modules\ConfigObjects\Command::PHP_TYPE,
                    ])) {
                        throw new \Exception('Invalid command type: ' . $this->command->signature . ' ' . $this->command->type);
                    }

                    if ($this->command->type==\App\Modules\ConfigObjects\Command::ARTISAN_TYPE) {

                        $runnable = ['php', 'artisan', ...$this->command->command];
                        $pod = $this->clusterManager->findPodByLabelsAndNamespace($config->getDevContainerLabels(), $this->namespace);
                        return $this->clusterManager->execDevCommand($this->namespace, $pod, $runnable, $this->command->debug);

                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::FROCK_TYPE) {
                        $runnable = ['php', 'vendor/bin/frock.php', ...$this->command->command];
                        $pod = $this->clusterManager->findPodByLabelsAndNamespace($config->getDevContainerLabels(), $this->namespace);
                        return $this->clusterManager->execDevCommand($this->namespace, $pod, $runnable, $this->command->debug);

                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::COMPOSER_TYPE) {

                        $runnable = ['composer', ...$this->command->command];
                        $pod = $this->clusterManager->findPodByLabelsAndNamespace($config->getDevContainerLabels(), $this->namespace);
                        return $this->clusterManager->execDevCommand($this->namespace, $pod, $runnable, $this->command->debug);

                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::PHP_TYPE) {

                        $runnable = ['php', ...$this->command->command];
                        $pod = $this->clusterManager->findPodByLabelsAndNamespace($config->getDevContainerLabels(), $this->namespace);
                        return $this->clusterManager->execDevCommand($this->namespace, $pod, $runnable, $this->command->debug);

                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::BASH_TYPE) {

                        $runnable = $this->command->command;
                        $pod = $this->clusterManager->findPodByLabelsAndNamespace($config->getDevContainerLabels(), $this->namespace);
                        return $this->clusterManager->execDevCommand($this->namespace, $pod, $runnable, $this->command->debug);

                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::RAW_TYPE) {
                        $runnable = $this->command->command;
                        $process = new Process($runnable);
                        $process->setTty($config->getTtyEnabled());
                        $process->setIdleTimeout(null);
                        $process->setTimeout(null);
                        $process->run();
                        if (!$config->getTtyEnabled()) {
                            if ($process->isSuccessful()) {
                                echo $process->getOutput();
                            } else {
                                echo $process->getOutput();
                                echo $process->getErrorOutput();
                            }
                        }
                        return $process->getExitCode();
                    }
                    throw new \Exception('Command was not executed: ' . $this->command->signature . ' ' . $this->command->type);
                }
            };
        }

        $this->commands($resultCommands);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerCustomCommands();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Helpers/helpers.php');
        $this->app->singleton(Config::class, Config::class);
        $this->app->singleton(KubernetesClusterManager::class, KubernetesClusterManager::class);
    }
}
