<?php

namespace App;

use Illuminate\Console\Command;

class CurCom
{
    private static Command $command;

    public static function get(): Command
    {
        if (!self::$command) {
            throw new \Exception('Command not set');
        }
        return self::$command;
    }

    public static function setCommand(Command $command): void
    {
        self::$command = $command;
    }
}
