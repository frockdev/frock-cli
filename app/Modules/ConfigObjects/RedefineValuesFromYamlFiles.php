<?php

namespace App\Modules\ConfigObjects;

class RedefineValuesFromYamlFiles
{
    public function __construct(
        public readonly string $yamlFile,
        public readonly array $fromYamlToHelm = []
    )
    {
    }
}
