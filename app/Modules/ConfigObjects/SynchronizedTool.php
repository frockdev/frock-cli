<?php

namespace App\Modules\ConfigObjects;


//type SynchronizedTool struct {
//	Link         string     `json:"link"`
//	Name         string     `json:"name"`
//	Version      string     `json:"version"`
//	ExcludePaths []string   `json:"excludePaths,omitempty"`
//	MovePaths    []MovePath `json:"movePaths,omitempty"`
//  OnlyPaths    []string   `json:"onlyPaths,omitempty"`
//	Gitignore    []string   `json:"gitignore,omitempty"`
//}
//
//type MovePath struct {
//	From string `json:"from"`
//	To   string `json:"to"`
//}

class SynchronizedTool
{
    /**
     * @param string $link
     * @param string $name
     * @param string $version
     * @param array|string[] $excludePaths
     * @param array|MovePath[] $movePaths
     * @param array|string[] $gitignore
     * @param array|CopyPath[] $copyPaths
     * @param array $onlyPaths
     */
    public function __construct(
        public readonly string $link,
        public readonly string $name,
        public readonly string $version,
        public readonly array $excludePaths,
        public readonly array $movePaths,
        public readonly array $gitignore,
        public readonly array $copyPaths,
        public readonly array $onlyPaths,
    )
    {
    }
}
