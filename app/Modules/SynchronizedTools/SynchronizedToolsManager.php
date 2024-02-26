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
}
