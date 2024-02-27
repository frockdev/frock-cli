<?php

namespace App\Modules\ConfigObjects;

class DevelopmentPackage
{
    public function __construct(
        public readonly string $sshLink,
        public readonly string $branch,
        public readonly string $composerPackageName,
        public readonly string $shortName,
        public readonly bool   $pushWhenSwitchingOff = false,
    )
    {
    }
}
