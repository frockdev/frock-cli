<?php

namespace App\Modules\PackagesTool;

use App\Modules\ConfigObjects\DevelopmentPackage;
use App\Modules\Kubernetes\KubernetesClusterManager;
use App\Modules\ProjectConfig\Config;
use CzProject\GitPhp\Git;

class DevelopmentPackagesManager
{
    private Config $config;
    private KubernetesClusterManager $kubernetesClusterManager;

    public function __construct(Config $config, KubernetesClusterManager $kubernetesClusterManager)
    {
        $this->config = $config;
        $this->kubernetesClusterManager = $kubernetesClusterManager;
    }

    public function detectIfPackageEnabledForDeveloping(DevelopmentPackage $package)
    {
        if (file_exists($this->config->getWorkingDir() . '/packages/' . $package->shortName)) {
            return true;
        } else {
            return false;
        }
    }

    public function getStabilityDependsAnyPackagesThere() {
        foreach ($this->config->getDevelopmentPackages() as $package) {
            if (file_exists($this->config->getWorkingDir().'/packages/'.$package->shortName)) {
                return 'dev';
            }
        }
        return 'stable';
    }

    public function installPackageInNormalMode(DevelopmentPackage $currentPackage)
    {
        $git = new Git();
        $repo = $git->open($this->config->getWorkingDir() . '/packages/' . $currentPackage->shortName);
        $currentBranchName = $repo->getCurrentBranchName();
        if ($repo->hasChanges() && !$currentPackage->pushWhenSwitchingOff) {
            throw new \Exception('You have uncommitted changes in package. Please commit and push or revert them');
        }
        if ($repo->hasChanges() && $currentPackage->pushWhenSwitchingOff) {
            $repo->createBranch('changes-'.$currentPackage->branch.'-'.$this->config->getDeveloperName(), true);
            $currentBranchName = $repo->getCurrentBranchName();
            $repo->commit('Auto switching off development mode');
        }
        if ($currentPackage->pushWhenSwitchingOff) {
            $repo->push($currentBranchName, ['--force-with-lease', '--set-upstream', 'origin']);
        }
        $composerJson = json_decode(file_get_contents($this->config->getWorkingDir() . '/php/composer.json'), true);
        if (isset($composerJson['repositories'][$currentPackage->shortName])) {
            unset($composerJson['repositories'][$currentPackage->shortName]);
        }
        unset($composerJson['require'][$currentPackage->composerPackageName]);
        shell_exec('rm -rf '.$this->config->getWorkingDir() . '/packages/' . $currentPackage->shortName);
        $composerJson['minimum-stability'] = $this->getStabilityDependsAnyPackagesThere();
        $resultJson = json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        file_put_contents($this->config->getWorkingDir() . '/php/composer.json', $resultJson);

        $podName = $this->kubernetesClusterManager->findPodByLabelAndNamespace('containerForDeveloper', 'true', $this->config->getNamespace());
        $this->kubernetesClusterManager->execDevCommand($this->config->getNamespace(), $podName, ['composer', 'require', $currentPackage->composerPackageName]);


    }

    public function enablePackageForDeveloping(DevelopmentPackage $currentPackage)
    {
        @mkdir($this->config->getWorkingDir() . '/packages/');
        $git = new Git();
        $repo = $git->cloneRepository($currentPackage->sshLink, $this->config->getWorkingDir() . '/packages/' . $currentPackage->shortName);
        $repo->checkout($currentPackage->branch);
        $composerJson = json_decode(file_get_contents($this->config->getWorkingDir() . '/php/composer.json'), true);

        $composerJson['repositories'][$currentPackage->shortName] = [
            'type' => 'path',
            'url' => '../packages/'.$currentPackage->shortName,
            "options" => [
                "symlink" => true
            ]
        ];

        $composerJson['minimum-stability'] = $this->getStabilityDependsAnyPackagesThere();

        $composerJson['require'][$currentPackage->composerPackageName] = 'dev-'.$currentPackage->branch;

        $resultJson = json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        file_put_contents($this->config->getWorkingDir() . '/php/composer.json', $resultJson);

        $podName = $this->kubernetesClusterManager->findPodByLabelAndNamespace('containerForDeveloper', 'true', $this->config->getNamespace());
        $this->kubernetesClusterManager->execDevCommand($this->config->getNamespace(), $podName, ['composer', 'require', $currentPackage->composerPackageName]);
    }


}
