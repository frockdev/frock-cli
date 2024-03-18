<?php

namespace App\Commands\SynchronizedTools;

use App\Modules\ProjectConfig\Config;
use App\Modules\SynchronizedTools\SynchronizedToolsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BumpPatch extends Command
{
    protected $signature = 'tools:patch {tool?} {gitlabUrl?} {repoUrl?}';

    protected $description = 'Bump patch version of all synchronized tools, or specified one by name';

    public function handle(Config $config, SynchronizedToolsManager $manager)
    {
        $gitlabBody = '';
        $tools = $config->getSynchronizedTools();
        if ($this->argument('tool')) {
            $this->info('Bumping patch version of ' . $this->argument('tool'));
            $newVersion = $manager->findHighestToolPatchVersion($tools[$this->argument('tool')] ?? throw new \Exception('Tool not installed'));
            $oldVersion = $config->getCurrentVersionOfTool($this->argument('tool'));
            $config->setNewSynchronizedToolsetVersion($this->argument('tool'), $newVersion);
            if ($newVersion!=$oldVersion) {
                $gitlabBody.= 'Bumped patch version of ' . $this->argument('tool') . ' from ' . $oldVersion . ' to ' . $newVersion . "\n";
            }
            Artisan::call('tools:install', ['tool'=>$this->argument('tool')], $this->output);
            if ($this->argument('gitlabUrl')) {
                $manager->createGitlabMergeRequest($gitlabBody, $this->argument('gitlabUrl'), $this->argument('repoUrl'));
            }
        } else {
            $this->info('Bumping patch version of all synchronized tools');
            foreach ($tools as $tool) {
                $this->info('Bumping patch version of ' . $tool->name);
                $newVersion = $manager->findHighestToolPatchVersion($tool);
                $oldVersion = $config->getCurrentVersionOfTool($tool->name);
                $config->setNewSynchronizedToolsetVersion($tool->name, $newVersion);
                if ($newVersion!=$oldVersion) {
                    $gitlabBody.= 'Bumped patch version of ' . $tool->name . ' from ' . $oldVersion . ' to ' . $newVersion . "\n";
                }
                Artisan::call('tools:install', ['tool'=>$tool->name], $this->output);
                if ($this->argument('gitlabUrl')) {
                    $manager->createGitlabMergeRequest($gitlabBody, $this->argument('gitlabUrl'), $this->argument('repoUrl'));
                }
            }
        }
    }
}
