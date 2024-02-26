<?php

namespace App\Modules\ConfigObjects;

class MovePath
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    )
    {

    }
}
