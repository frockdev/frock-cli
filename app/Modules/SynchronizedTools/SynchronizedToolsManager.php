<?php

namespace App\Modules\SynchronizedTools;

use App\Modules\ConfigObjects\SynchronizedTool;
use App\Modules\ProjectConfig\Config;

class SynchronizedToolsManager
{
    /**
     * // find all folders in current cwd()
     * // if folder ends with -frock-tool
     * // remove folder
     * @return void
     */
    public function cleanAllTools() {
        $folders = scandir(getcwd());
        foreach ($folders as $folder) {
            if (str_ends_with($folder, '-synchronized-tool')) {
                shell_exec('rm -rf ' . $folder);
            }
        }
    }

    public function cleanToolByName(string $name) {
        shell_exec('rm -rf ' . getcwd().'/'.$name.'-synchronized-tool');
    }

    public function installTool(SynchronizedTool $tool) {
        $config = app()->make(Config::class);
        $git = new \CzProject\GitPhp\Git;
        $toolDir = $config->getWorkingDir().'/'. $tool->name.'-synchronized-tool';
        $repo = $git->cloneRepository($tool->link, $toolDir);

        $repo->checkout($tool->version);
        foreach ($tool->excludePaths as $excludePath) {
            shell_exec('rm -rf '.$toolDir.'/'.$excludePath);
        }

        foreach ($tool->copyPaths as $copyPath) {
            shell_exec('cp -r '.$toolDir.'/'.$copyPath->from.' '.$toolDir.'/'.$copyPath->to);
        }

        foreach ($tool->movePaths as $movePath) {
            shell_exec('mv '.$toolDir.'/'.$movePath->from.' '.$toolDir.'/'.$movePath->to);
        }

        file_put_contents($toolDir.'/.gitignore', PHP_EOL , FILE_APPEND | LOCK_EX);
        foreach ($tool->gitignore as $gitignore) {
            file_put_contents($toolDir.'/.gitignore', $gitignore.PHP_EOL , FILE_APPEND | LOCK_EX);
        }
    }

    public function findHighestToolPatchVersion(SynchronizedTool $tool)
    {
        $config = app()->make(Config::class);
        $git = new \CzProject\GitPhp\Git;
        $tmpToolDir = $config->getWorkingDir().'/'. $tool->name.'-tmp-synchronized-tool';
        shell_exec('rm -rf '.$tmpToolDir);
        $repo = $git->cloneRepository($tool->link, $tmpToolDir);
        $tags = $repo->getTags();
        shell_exec('rm -rf '.$tmpToolDir);
        $currentVersion = $tool->version;
        return $this->findHighestPatchTag($tags, $currentVersion);
    }

    /**
     * This function should find highest patch version from $tags array
     * @param array $tags
     * @param string $currentVersion
     * @return string
     */
    public function findHighestPatchTag(array $tags, string $currentVersion): string {
        $vIsFirst = false;
        if (str_starts_with($currentVersion, 'v')) {
            $vIsFirst = true;
            $currentVersion = substr($currentVersion, 1);
        }
        $currentVersion = explode('.', $currentVersion);
        $currentVersion = array_map('intval', $currentVersion);
        $highestPatch = 0;
        foreach ($tags as $tag) {
            if ($vIsFirst) {
                $tag = substr($tag, 1);
            }
            $tag = explode('.', $tag);
            $tag = array_map('intval', $tag);
            if ($tag[0] === $currentVersion[0] && $tag[1] === $currentVersion[1] && $tag[2] >= $currentVersion[2]) {
                if ($tag[2] >= $highestPatch) {
                    $highestPatch = $tag[2];
                }
            }
        }
        unset($currentVersion[2]);
        $highestPatchedVersion = implode('.', $currentVersion).'.'.$highestPatch;
        if ($vIsFirst) {
            return 'v'.$highestPatchedVersion;
        }
        return $highestPatchedVersion;
    }
}
