<?php

namespace App\Modules\ConfigObjects;

class ChartRemote
{
    public function __construct(
        public readonly string $repoUrl,
        public readonly string $version,
        public readonly string $chartName,
    )
    {
    }
}
