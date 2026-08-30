<?php

use Laravel\Octane\Listeners\CollectGarbage;

test('production PHP limits accept the complete image editor payload', function () {
    $configuration = parse_ini_file(base_path('docker/php-production.ini'));

    expect($configuration)->toBeArray()
        ->and($configuration['upload_max_filesize'])->toBe('8M')
        ->and($configuration['post_max_size'])->toBe('20M')
        ->and($configuration['memory_limit'])->toBe('512M');
});

test('the web container warms Laravel and bounds long-lived Octane workers', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));
    $command = file_get_contents(base_path('docker/app-command.sh'));

    expect($dockerfile)
        ->toContain('COPY --chmod=644 docker/php-production.ini /usr/local/etc/php/conf.d/zz-shoutrrr-production.ini')
        ->toContain('CMD ["/usr/local/bin/app-command.sh"]')
        ->and($command)
        ->toContain('artisan optimize --no-ansi --no-interaction')
        ->toContain('--workers="$OCTANE_WORKERS_VALUE"')
        ->toContain('--max-requests="$OCTANE_MAX_REQUESTS_VALUE"')
        ->and(config('octane.listeners')[\Laravel\Octane\Contracts\OperationTerminated::class])
        ->toContain(CollectGarbage::class);
});

test('the production deployment pins the reviewed runtime budgets', function () {
    $workflow = file_get_contents(base_path('.github/workflows/deploy.yml'));

    expect($workflow)
        ->toContain('OCTANE_WORKERS: "2"')
        ->toContain('OCTANE_MAX_REQUESTS: "250"')
        ->toContain('PHP_MEMORY_LIMIT: "512M"')
        ->toContain('PHP_POST_MAX_SIZE: "20M"')
        ->toContain('PHP_UPLOAD_MAX_FILE_SIZE: "8M"');
});
