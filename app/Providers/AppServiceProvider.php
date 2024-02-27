<?php

namespace App\Providers;

use App\Modules\Kubernetes\KubernetesClusterManager;
use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;

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

                public function handle() {

                    if ($this->command->type==\App\Modules\ConfigObjects\Command::ARTISAN_TYPE) {
//                        $runnable = $this->kubectlExec. ' php ./artisan ' . $this->command->command;
                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::FROCK_TYPE) {
//                        $runnable = $this->kubectlExec. ' php ./vendor/bin/frock.php ' . $this->command->command;
                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::COMPOSER_TYPE) {
//                        $runnable = $this->kubectlExec. ' composer ' . $this->command->command;
                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::PHP_TYPE) {
//                        $runnable = $this->kubectlExec. ' php ' . $this->command->command;
                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::BASH_TYPE) {
                        $runnable = $this->command->command;
                        $pod = $this->clusterManager->findPodByLabelAndNamespace('containerForDeveloper', 'true', $this->namespace);
                        $this->clusterManager->execDevCommand($this->namespace, $pod, $runnable, $this->command->debug);
                    } elseif ($this->command->type==\App\Modules\ConfigObjects\Command::RAW_TYPE) {
                        $runnable = $this->command->command;
                    }
                    if (!$runnable) {
                        throw new \Exception('Invalid command type: ' . $this->command->signature . ' ' . $this->command->type);
                    }
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
