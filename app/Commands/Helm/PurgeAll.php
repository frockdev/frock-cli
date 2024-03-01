<?php

namespace App\Commands\Helm;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PurgeAll extends Command
{
    protected $signature = 'down-all';

    protected $description = 'Purge application and all boxes';

    public function handle() {
        Artisan::call('purge', [], $this->output);
        Artisan::call('box:purge-all', [], $this->output);
    }
}
