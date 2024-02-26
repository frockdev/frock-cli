<?php

namespace App\Modules\ConfigObjects;

class CopyPath
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    )
    {

    }
}
