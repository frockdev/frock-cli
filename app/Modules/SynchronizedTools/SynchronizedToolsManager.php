<?php

namespace App\Modules\SynchronizedTools;

use App\CurCom;
use App\Modules\ConfigObjects\SynchronizedTool;
use App\Modules\ProjectConfig\Config;
use CzProject\GitPhp\Git;
use Illuminate\Support\Facades\Http;

class SynchronizedToolsManager
{

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

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

    /**
     * @param SynchronizedTool $tool
     * @return void
     */
    public function push(SynchronizedTool $tool)
    {
        try {
            $copiedToolDir = $this->config->getWorkingDir() . '/' . $tool->name . '-copied-synchronized-tool';
            shell_exec('rm -rf ' . $copiedToolDir);
            shell_exec('cp -r ' . $this->config->getWorkingDir() . '/' . $tool->name . '-synchronized-tool' . ' ' . $copiedToolDir);
            $tmpToolDir = $this->config->getWorkingDir() . '/' . $tool->name . '-tmp-synchronized-tool';
            shell_exec('rm -rf ' . $tmpToolDir);
            $git = new \CzProject\GitPhp\Git;
            $repo = $git->cloneRepository($tool->link, $tmpToolDir);
            $repo->checkout($tool->version);

            //move back
            foreach ($tool->movePaths as $movePath) {
                shell_exec('mv ' . $copiedToolDir . '/' . $movePath->to . ' ' . $copiedToolDir . '/' . $movePath->from);
            }

            //copy back. simply remove copies
            foreach ($tool->copyPaths as $copyPath) {
                shell_exec('rm -rf ' . $copiedToolDir . '/' . $copyPath->to);
            }

            //restore .gitignore
            $gitignore = file($copiedToolDir . '/.gitignore', FILE_IGNORE_NEW_LINES);
            $gitignore = array_reverse($gitignore);
            $gitignoreLines = array_reverse($tool->gitignore);
            foreach ($gitignore as $key=>$line) {
                if (in_array($line, $gitignoreLines)) {
                    unset($gitignoreLines[array_search($line, $gitignoreLines)]);
                    unset($gitignore[$key]);
                }
            }
            $gitignore = array_reverse($gitignore);
            file_put_contents($copiedToolDir . '/.gitignore', implode("\n", $gitignore));

            foreach ($tool->excludePaths as $excludePath) {
                shell_exec('rm -rf ' . $copiedToolDir . '/' . $excludePath);
            }

            $this->copyFiles($copiedToolDir, $tmpToolDir);

            $repo->addAllChanges('.');
            $branchName = 'changes-' . $tool->version . '-by-' . $this->config->getDeveloperName();
            $repo->createBranch($branchName, true);
            if ($repo->hasChanges()) {
                $repo->commit('Changes by ' . $this->config->getDeveloperName());
                $repo->push($branchName, ['--force-with-lease', '--set-upstream', 'origin']);
            } else {
                CurCom::get()->info('No changes to push');
            }
        } finally {
            shell_exec('rm -rf ' . $copiedToolDir);
            shell_exec('rm -rf ' . $tmpToolDir);
        }

    }

    public function installTool(SynchronizedTool $tool) {
        $config = app()->make(Config::class);
        $git = new \CzProject\GitPhp\Git;
        $toolDir = $config->getWorkingDir().'/'. $tool->name.'-synchronized-tool';
        $repo = $git->cloneRepository($tool->link, $toolDir);

        $repo->checkout($tool->version);
        if (count($tool->excludePaths)>0 && count($tool->onlyPaths)===0) {
            foreach ($tool->excludePaths as $excludePath) {
                shell_exec('rm -rf '.$toolDir.'/'.$excludePath);
            }
        } elseif (count($tool->onlyPaths)>0) {
            $this->removeAllExcept($toolDir, $tool->onlyPaths);
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

    public function findHighestToolMajorVersion(SynchronizedTool $tool) {
        $config = app()->make(Config::class);
        $git = new \CzProject\GitPhp\Git;
        $tmpToolDir = $config->getWorkingDir().'/'. $tool->name.'-tmp-synchronized-tool';
        shell_exec('rm -rf '.$tmpToolDir);
        $repo = $git->cloneRepository($tool->link, $tmpToolDir);
        $tags = $repo->getTags();
        shell_exec('rm -rf '.$tmpToolDir);
        $currentVersion = $tool->version;
        return $this->findHighestMajorTag($tags, $currentVersion);
    }

    public function findHighestToolMinorVersion(SynchronizedTool $tool) {
        $config = app()->make(Config::class);
        $git = new \CzProject\GitPhp\Git;
        $tmpToolDir = $config->getWorkingDir().'/'. $tool->name.'-tmp-synchronized-tool';
        shell_exec('rm -rf '.$tmpToolDir);
        $repo = $git->cloneRepository($tool->link, $tmpToolDir);
        $tags = $repo->getTags();
        shell_exec('rm -rf '.$tmpToolDir);
        $currentVersion = $tool->version;
        return $this->findHighestMinorTag($tags, $currentVersion);
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

    /**
     * @param string $baseDir
     * @param array $onlyPaths
     * @return void
     * This function should recursively scan directory and remove all files except those in $onlyPaths
     */
    private function removeAllExcept(string $baseDir, array $onlyPaths)
    {
        $files = scandir($baseDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_dir($baseDir.'/'.$file)) {
                $this->removeAllExcept($baseDir.'/'.$file, $onlyPaths);
            } else {
                if (!in_array($file, $onlyPaths)) {
                    shell_exec('rm -rf '.$baseDir.'/'.$file);
                }
            }
        }
    }

    private function findHighestMajorTag(array $tags, string $currentVersion) {
        $vIsFirst = false;
        if (str_starts_with($currentVersion, 'v')) {
            $vIsFirst = true;
            $currentVersion = substr($currentVersion, 1);
        }
        $currentVersion = explode('.', $currentVersion);
        $currentVersion = array_map('intval', $currentVersion);
        $highestMajor = 0;
        foreach ($tags as $tag) {
            if ($vIsFirst) {
                $tag = substr($tag, 1);
            }
            $tag = explode('.', $tag);
            $tag = array_map('intval', $tag);
            if ($tag[0] >= $currentVersion[0]) {
                if ($tag[0] >= $highestMajor) {
                    $highestMajor = $tag[0];
                }
            }
        }
        unset($currentVersion[0]);
        $highestMajoredVersion = $highestMajor.'.0.0';
        if ($vIsFirst) {
            return 'v'.$this->findHighestMinorTag($tags, $highestMajoredVersion);
        }
        return $this->findHighestMinorTag($tags, $highestMajoredVersion);
    }

    private function findHighestMinorTag(?array $tags, string $currentVersion)
    {
        $vIsFirst = false;
        if (str_starts_with($currentVersion, 'v')) {
            $vIsFirst = true;
            $currentVersion = substr($currentVersion, 1);
        }
        $currentVersion = explode('.', $currentVersion);
        $currentVersion = array_map('intval', $currentVersion);
        $highestMinor = 0;
        foreach ($tags as $tag) {
            if ($vIsFirst) {
                $tag = substr($tag, 1);
            }
            $tag = explode('.', $tag);
            $tag = array_map('intval', $tag);
            if ($tag[0] === $currentVersion[0] && $tag[1] >= $currentVersion[1]) {
                if ($tag[1] >= $highestMinor) {
                    $highestMinor = $tag[1];
                }
            }
        }
        unset($currentVersion[1]);
        $highestMinoredVersion = $currentVersion[0].'.'.$highestMinor.'.0';
        if ($vIsFirst) {
            return 'v'.$this->findHighestPatchTag($tags, $highestMinoredVersion);
        }
        return $this->findHighestPatchTag($tags, $highestMinoredVersion);
    }

    /**
     * @param string $copiedToolDir
     * @param string $tmpToolDir
     * @return void
     * // after that we take each file recursively in $copiedToolDir directory and copy it to the $tmpToolDir
     * //  if file already exists in $tmpToolDir we remove it
     */
    private function copyFiles(string $copiedToolDir, string $tmpToolDir)
    {
        $files = scandir($copiedToolDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_dir($copiedToolDir.'/'.$file)) {
                $this->copyFiles($copiedToolDir.'/'.$file, $tmpToolDir.'/'.$file);
            } else {
                shell_exec('rm -rf '.$tmpToolDir.'/'.$file);
                shell_exec('cp '.$copiedToolDir.'/'.$file.' '.$tmpToolDir.'/'.$file);
            }
        }
    }

    public function createGitlabMergeRequest(string $gitlabBody, string $gitlabUrl, string $repoUrl)
    {
        try {
        $git = new Git();
        $repo = $git->open($this->config->getWorkingDir());
        $oldBranch = $repo->getCurrentBranchName();
        if ($repo->hasChanges()) {
            $sha1 = sha1($gitlabBody);
            try {
                echo 'Creating merge request for tools-update-'. $sha1 ."\n";
                $repo->execute('branch', '-D', 'tools-update-'. $sha1);
                echo 'Branch deleted'."\n";
            } catch (\Throwable $e) {
                echo 'Error: '. $e->getMessage()."\n";
            }

            try {
                $repo->removeRemote('origin2');
                echo 'Remote removed'."\n";
            } catch (\Throwable $e) {
                echo 'Error: '. $e->getMessage()."\n";
            }

            $repo->createBranch('tools-update-'. $sha1, true);
            echo 'Branch created'."\n";
            $repo->addAllChanges();
            echo 'Changes added'."\n";

            $repo->addRemote('origin2', $repoUrl);
            echo 'Remote added'."\n";
            $repo->commit('Changes by automated frock run');
            echo 'Changes commited'."\n";
            $repo->push('tools-update-'. $sha1, ['--force', '--set-upstream', 'origin2']);
            echo 'Changes pushed'."\n";
            $repo->removeRemote('origin2');
            echo 'Remote removed'."\n";

            echo 'Checking for existing merge request'."\n";

            $url = $gitlabUrl.'/api/v4/projects/'.getenv('CI_PROJECT_ID').'/merge_requests?state=opened';
            $cut = str_replace('https://', '', $url);
            $cut = explode('@', $cut);
            $cut = explode(':', $cut[0]);
            $user = $cut[0];
            $password = $cut[1];

            $url = str_replace($user . ':' . $password . '@', '', $url);

            $branches = Http::withHeader('Authorization', 'Bearer '.$password)->get($url);

            var_dump($branches->body());
            if ($branches->json('message') && $branches->json('message') === '404 Branch Not Found') {
                echo 'Project not found, maybe auth problem'."\n";
                return;
            }
            foreach ($branches->json() as $branchInfo) {
                if ($branchInfo['source_branch'] === 'tools-update-'. $sha1) {
                    return;
                }
            }
            echo 'Creating merge request'."\n";

            $url = $gitlabUrl.'/api/v4/projects/'.getenv('CI_PROJECT_ID').'/merge_requests';
            $url = str_replace($user . ':' . $password . '@', '', $url);
            $gitlabBody = 'Automated frock run'."\n".$gitlabBody;
            echo 'Creating merge request'."\n";
            $data = [
                'source_branch' => 'tools-update-'. $sha1,
                'remove_source_branch' => 'true',
                'target_branch' => 'main',
                'title' => 'Automated frock run',
                'description' => $gitlabBody
            ];
            var_dump($data);
            var_dump($url);
            var_dump($password);
            $response = Http::withHeader('Authorization', 'Bearer '.$password)->post($url, $data);
            var_dump($response->json());
        }

        } catch (\Throwable $e) {
            var_dump($e);
            throw ($e);
        } finally {
            try {
                $repo->removeRemote('origin2');
            } catch (\Throwable $e) {
                echo 'Error: '. $e->getMessage()."\n";
            }

            $repo->checkout($oldBranch);
        }
    }


}
