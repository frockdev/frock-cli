<?php

namespace App\Modules\ConfigObjects;

class DevelopmentPackage
{
    public function __construct(
        public readonly string $sshLink,
        public readonly string $devName,
        public readonly string $branchOrTag,
    )
    {
    }
}
