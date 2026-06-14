<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$start = microtime(true);
app('db')->connection()->getPdo();
echo "DB Connect: " . ((microtime(true) - $start) * 1000) . " ms\n";
