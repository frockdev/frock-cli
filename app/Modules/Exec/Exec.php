<?php

namespace App\Modules\Exec;

class Exec
{
    public static function run(string $command) {
        return shell_exec($command);
    }
}
