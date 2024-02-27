<?php

function yaml_parse(string $yaml): array {
    return \Symfony\Component\Yaml\Yaml::parse($yaml);
}

function yaml_parse_file(string $filename): array {
    return \Symfony\Component\Yaml\Yaml::parseFile($filename);
}
