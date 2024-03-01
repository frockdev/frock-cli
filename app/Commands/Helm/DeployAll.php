<?php

namespace App\Commands\Helm;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DeployAll extends Command
{
    protected $signature = 'up-all';

    protected $description = 'Deploy application and all boxes';

    public function handle() {
        Artisan::call('deploy', [], $this->output);
        Artisan::call('box:deploy-all', [], $this->output);
    }
}
