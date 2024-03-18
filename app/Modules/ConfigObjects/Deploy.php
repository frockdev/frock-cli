<?php

namespace App\Modules\ConfigObjects;

class Deploy
{

    public function __construct(
        public readonly string      $namespace,
        public readonly string      $appEnvironment,
        public readonly string $finalValuesFilePath,
        public readonly bool $combineFinalValuesFromEnvAndOverrides,

        public readonly array $redefineValuesFromEnvVariables = [],
        /**
         * @var array<string, string>|RedefineValuesFromYamlFiles[]
         */
        public readonly array $redefineValuesFromYamlFiles = [],
        public readonly ?ChartLocal      $chartLocal = null,
        public readonly ?ChartRemote $chartRemote = null,
        public readonly ?string      $valuesByEnv = ''
    )
    {
    }
}
