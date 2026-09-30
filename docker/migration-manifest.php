<?php

declare(strict_types=1);

use App\Support\MigrationManifest;
use Illuminate\Contracts\Console\Kernel;

require getcwd().'/vendor/autoload.php';
require_once __DIR__.'/MigrationManifest.php';

$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$manifest = $app->make(MigrationManifest::class);

echo json_encode(['hash' => $manifest->hash(), 'files' => $manifest->files()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
