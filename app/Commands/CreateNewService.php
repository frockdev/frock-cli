<?php

namespace App\Commands;

use App\Modules\ProjectConfig\Config;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;
use function Termwind\render;

class CreateNewService extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'initialize';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new service.';

    /**
     * Execute the console command.
     */
    public function handle(Config $config): void
    {
        $this->info('Initializing fresh laravel project...');
        $docker = explode(' ', "docker run --rm -v ".$config->getWorkingDir().":/var/www -w /var/www --entrypoint bash vladitot/php83-swow-ubuntu-local -c");
        $cmd = "composer create-project --prefer-dist laravel/laravel php";
        $process = new Process([...$docker, $cmd]);
        $process->setTty($config->getTtyEnabled());
        $process->run();

        $this->info('Installing frock-tools...');
        $docker = explode(' ', "docker run --rm -v ".$config->getWorkingDir().":/var/www -w /var/www/php --entrypoint bash vladitot/php83-swow-ubuntu-local -c");
        $cmd = "composer require frock-dev/tools-for-laravel:^0.0";
        $process = new Process([...$docker, $cmd]);
        $process->setTty($config->getTtyEnabled());
        $process->run();
    }
}
