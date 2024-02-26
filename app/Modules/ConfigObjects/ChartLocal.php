<?php

namespace App\Modules\ConfigObjects;

class ChartLocal
{
    public function __construct(
        public readonly string $chartPath,
        public readonly ?string $appVersion,
        public readonly ?string $chartVersion,
    )
    {
    }
}
