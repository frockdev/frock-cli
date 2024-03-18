<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestClass extends Command
{
    protected $signature = 'test:class';
    public function handle() {

//                dd($response->json());
    }
}
