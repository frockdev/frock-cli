<?php

namespace App\Commands;

use App\Modules\ProjectConfig\Config;
use Illuminate\Console\Command;

class PublishSchema extends Command
{
    protected $signature = 'schema';
    protected $description = 'Publish frock.yaml json schema';

    public function handle(Config $config) {
        file_put_contents(
            $config->getWorkingDir().'/frock.schema.json',
          file_get_contents(app_path().'/schema/frock.schema.json')
        );
    }
}
