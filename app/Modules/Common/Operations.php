<?php

namespace App\Modules\Common;

use App\Modules\Exec\Exec;
use App\Modules\ProjectConfig\Config;

class Operations
{

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function createValuesFiles() {
        if (!file_exists(getcwd().'/values')) {
            mkdir(getcwd().'/values');
        }

        if (!file_exists(getcwd().'/values/.gitignore')) {
            touch(getcwd().'/values/.gitignore');
            file_put_contents(getcwd().'/values/.gitignore', "\n".'override.values.yaml');
            file_put_contents(getcwd().'/values/.gitignore', "\n".'secrets.values.yaml', FILE_APPEND);
        }

        touch(getcwd().'/values/common.values.yaml');
        touch(getcwd().'/values/local.values.yaml');
        touch(getcwd().'/values/override.values.yaml');
        touch(getcwd().'/values/preprod.values.yaml');
        touch(getcwd().'/values/tests.values.yaml');
        touch(getcwd().'/values/prod.values.yaml');
        touch(getcwd().'/values/rc.values.yaml');
        touch(getcwd().'/values/review.values.yaml');
        touch(getcwd().'/values/secrets.values.yaml');
    }

    public function createGitIgnore() {
        if (!file_exists(getcwd().'/.gitignore')) {
            touch (getcwd().'/.gitignore');
            file_put_contents(getcwd().'/.gitignore',
                '.env.infra.override'."\n"
                .'.devspace'."\n"
                .'.idea'."\n"
                .$this->config->getProjectName().'.iml');
        }
    }

    public function installLaravel(string $version='master') {
        $command = 'docker run --rm -v '.getcwd().'/php:/var/www --user='.$this->config->getCurrentUserId().' --workdir=/var/www --entrypoint=composer '.$this->config->getDevImage().' create-project --prefer-dist laravel/laravel:'.$version.' .';
        Exec::run($command);
    }

    public function installWholeToolset(string $version='master') {
        $this->installLaravel($version);
        //      devspace run update-to-latest-version
    }

    public function detectLatestVersion(): string {
        try {
            $helmChartRepository = $this->config->getHelmChartRepository();
            Exec::run('git clone "'.$helmChartRepository.'" '.getcwd().'/helm-cloned');
            $latestVersion = trim(Exec::run('cd helm-cloned && echo $(git tag -l --sort=-version:refname | head -n 1)'));
        } finally {
            Exec::run('rm -rf '.getcwd().'/helm-cloned');
        }

        return $latestVersion;
    }
}
