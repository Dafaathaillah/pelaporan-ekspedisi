<?php

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$app->make(BroadcastManager::class)
    ->connection('reverb')
    ->broadcast(['verification'], 'verification.ping', [
        'sent_at' => now()->toIso8601String(),
    ]);

fwrite(STDOUT, "REVERB_BROADCAST_OK\n");
