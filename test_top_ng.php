<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$prod = DB::connection('kelola')->table('productions')->where('status', 'ng')->first();
var_dump($prod->id);
