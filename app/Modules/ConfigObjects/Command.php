<?php


namespace App\Modules\ConfigObjects;
class Command
{
    public const ARTISAN_TYPE = 'artisan';
    public const FROCK_TYPE = 'frock';
    public const COMPOSER_TYPE = 'composer';
    public const RAW_TYPE = 'raw';

    public const BASH_TYPE = 'bash';
    public const PHP_TYPE = 'php';

    public function __construct(
        public readonly string $signature,
        public readonly string $type,
        public readonly string $command,
        public readonly string $description,
        public readonly bool $debug = false,
    )
    {
        if (!in_array($type, [self::ARTISAN_TYPE, self::FROCK_TYPE, self::COMPOSER_TYPE, self::RAW_TYPE, self::BASH_TYPE, self::PHP_TYPE])) {
            throw new \Exception('Invalid command type: ' . $this->signature . ' ' . $this->type);
        }
    }
}
