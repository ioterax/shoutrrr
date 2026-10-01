<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

it('compiles the Inertia root view during production optimization in a fresh process', function (): void {
    $directory = sys_get_temp_dir().'/shoutrrr-optimize-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($directory.'/views');

    try {
        $process = new Process([
            PHP_BINARY, 'artisan', 'optimize', '--no-ansi', '--no-interaction',
        ], base_path(), [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'SELF_HOSTED' => 'true',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'stderr',
            'INERTIA_SSR_ENABLED' => 'false',
            'PASSPORT_AUTO_GENERATE_KEYS' => 'false',
            'APP_CONFIG_CACHE' => $directory.'/config.php',
            'APP_EVENTS_CACHE' => $directory.'/events.php',
            'APP_ROUTES_CACHE' => $directory.'/routes.php',
            'APP_PACKAGES_CACHE' => $directory.'/packages.php',
            'APP_SERVICES_CACHE' => $directory.'/services.php',
            'VIEW_COMPILED_PATH' => $directory.'/views',
        ]);
        $process->mustRun();

        foreach (['config', 'events', 'routes'] as $cache) {
            expect($directory.'/'.$cache.'.php')->toBeFile();
        }

        $compiledRoot = collect(File::files($directory.'/views'))
            ->map(fn (SplFileInfo $file): string => File::get($file->getPathname()))
            ->first(fn (string $contents): bool => str_contains($contents, resource_path('views/app.blade.php')));

        expect($compiledRoot)->toBeString()
            ->toContain('Inertia\\View\\Components\\App', 'Inertia\\View\\Components\\Head');
    } finally {
        File::deleteDirectory($directory);
    }
});
