<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use function Termwind\render;

class CreateNewService extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'initialize {name=FrockExample}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new service.';

    /**
     * Execute the console command.
     */
    public function handle( ): void
    {




//        render(<<<'HTML'
//            <div class="py-1 ml-2">
//                <div class="px-1 bg-blue-300 text-black">Laravel Zero</div>
//                <em class="ml-1">
//                  Simplicity is the ultimate sophistication.
//                </em>
//            </div>
//        HTML);
    }
}
