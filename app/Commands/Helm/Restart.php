<?php

namespace App\Commands\Helm;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class Restart extends Command
{
    protected $signature = 'restart';

    protected $description = 'Redeploy application and all boxes.';

    public function handle() {
        $process = new Process(['frock', 'down-all']);
        $process->run();
        if ($process->isSuccessful()) {
            $this->info('Application and all boxes purged.');
        } else {
            $this->error('Failed to purge application and all boxes.');
        }
        $process = new Process(['frock', 'up']);
        $process->run();
        if ($process->isSuccessful()) {
            $this->info('Application and all boxes deployed.');
        } else {
            $this->error('Failed to deploy application and all boxes.');
        }
    }
}
