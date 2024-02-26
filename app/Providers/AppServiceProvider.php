<?php

namespace App\Providers;

use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    public function registerCustomCommands()
    {
        /** @var Config $config */
        $config = $this->app->make(Config::class);
        $resultCommands = [];
        /** @var \App\Modules\ConfigObjects\Command $command */
        foreach ($config->getCommands() as $command) {
            $resultCommands[] = new class($command) extends Command {
                private string $charge;

                public function __construct(\App\Modules\ConfigObjects\Command $command)
                {
                    $this->signature = $command->signature;
                    $this->description = $command->description;

                    if ($command->type==\App\Modules\ConfigObjects\Command::ARTISAN_TYPE) {
                        $this->charge = 'php ./artisan ' . $command->command;
                    } elseif ($command->type==\App\Modules\ConfigObjects\Command::FROCK_TYPE) {
                        $this->charge = 'php ./vendor/bin/frock.php ' . $command->command;
                    } elseif ($command->type==\App\Modules\ConfigObjects\Command::COMPOSER_TYPE) {
                        $this->charge = 'composer ' . $command->command;
                    } elseif ($command->type==\App\Modules\ConfigObjects\Command::RAW_TYPE) {
                        $this->charge = $command->command;
                    }

                    parent::__construct();
                }

                public function handle() {
                    echo shell_exec($this->charge.' 2>&1');
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
        $this->app->singleton(Config::class, Config::class);
    }
}
