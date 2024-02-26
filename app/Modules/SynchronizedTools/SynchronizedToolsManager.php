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
        $repo = $git->cloneRepository($tool->link, $config->getWorkingDir().'/'. $tool->name);

        $repo->checkout($tool->version);
        foreach ($tool->excludePaths as $excludePath) {
            shell_exec('rm -rf '.$config->getWorkingDir().'/'. $tool->name.'/'.$excludePath);
        }

        foreach ($tool->movePaths as $movePath) {
            shell_exec('mv '.$config->getWorkingDir().'/'. $tool->name.'/'.$movePath->from.' '.$config->getWorkingDir().'/'.$tool->name.'/'.$movePath->to);
        }

        foreach ($tool->gitignore as $gitignore) {
            file_put_contents($config->getWorkingDir().'/'. $tool->name.'/.gitignore', $gitignore.PHP_EOL , FILE_APPEND | LOCK_EX);
        }
    }
}
