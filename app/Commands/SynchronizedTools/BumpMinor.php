<?php

namespace App\Commands\SynchronizedTools;

use App\Modules\ProjectConfig\Config;
use App\Modules\SynchronizedTools\SynchronizedToolsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BumpMinor extends Command
{
    protected $signature = 'tools:minor {tool?} {gitlabUrl?} {repoUrl?}';

    protected $description = 'Bump minor version of all synchronized tools, or specified one by name';

    public function handle(Config $config, SynchronizedToolsManager $manager)
    {
        $gitlabBody = '';
        $tools = $config->getSynchronizedTools();
        if ($this->argument('tool')) {
            $this->info('Bumping minor version of ' . $this->argument('tool'));
            $newVersion = $manager->findHighestToolMinorVersion($tools[$this->argument('tool')] ?? throw new \Exception('Tool not installed'));
            $oldVersion = $config->getCurrentVersionOfTool($this->argument('tool'));

            $this->info('New version: ' . $newVersion);
            $this->info('Old version: ' . $oldVersion);
            $this->info('New bigger than old: ' . version_compare($newVersion, $oldVersion, '>'));
            if (version_compare($newVersion, $oldVersion, '>')) {
                $config->setNewSynchronizedToolsetVersion($this->argument('tool'), $newVersion);
                $gitlabBody.= 'Bumped minor version of ' . $this->argument('tool') . ' from ' . $oldVersion . ' to ' . $newVersion . "\n";
                Artisan::call('tools:install', ['tool'=>$this->argument('tool')], $this->output);
                if ($this->argument('gitlabUrl')) {
                    $manager->createGitlabMergeRequest($gitlabBody, $this->argument('gitlabUrl'), $this->argument('repoUrl'));
                }
            }

        } else {
            $this->info('Bumping minor version of all synchronized tools');
            foreach ($tools as $tool) {
                $this->info('Bumping minor version of ' . $tool->name);
                $newVersion = $manager->findHighestToolMinorVersion($tool);
                $oldVersion = $config->getCurrentVersionOfTool($this->argument('tool'));

                $this->info('New version: ' . $newVersion);
                $this->info('Old version: ' . $oldVersion);
                $this->info('New bigger than old: ' . version_compare($newVersion, $oldVersion, '>'));
                if (version_compare($newVersion, $oldVersion, '>')) {
                    $config->setNewSynchronizedToolsetVersion($tool->name, $newVersion);
                    $gitlabBody.= 'Bumped minor version of ' . $tool->name . ' from ' . $oldVersion . ' to ' . $newVersion . "\n";
                    Artisan::call('tools:install', ['tool'=>$tool->name], $this->output);
                    if ($this->argument('gitlabUrl')) {
                        $manager->createGitlabMergeRequest($gitlabBody, $this->argument('gitlabUrl'), $this->argument('repoUrl'));
                    }
                }

            }
        }
    }
}
