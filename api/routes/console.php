<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Security is not a product, it\'s a process.');
})->purpose('Display an inspiring quote about security');