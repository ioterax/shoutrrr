<?php

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

test('Redis stops waiting when a local server stalls the connection or read', function (string $connection, string $scheme) {
    $server = new Process([PHP_BINARY, base_path('tests/Fixtures/redis-silent-server.php')]);
    $server->setTimeout(15);
    $server->start();

    try {
        expect($server->waitUntil(fn (string $type, string $output): bool => str_contains($output, "\n")))->toBeTrue();
        [, $port] = explode(':', trim($server->getOutput()));
        $configuration = [
            ...config("database.redis.{$connection}"),
            'url' => null,
            'scheme' => $scheme,
            'host' => '127.0.0.1',
            'port' => (int) $port,
            'username' => null,
            'password' => null,
            'max_retries' => 0,
        ];
        config(["database.redis.{$connection}" => $configuration]);
        Exceptions::fake([RedisException::class]);
        Route::get('/__redis-stalled', fn () => app('redis')->connection($connection)->ping());
        $started = hrtime(true);

        $this->get('/__redis-stalled')
            ->assertServiceUnavailable()
            ->assertContent('Service temporarily unavailable. Please try again later.');

        $elapsedSeconds = (hrtime(true) - $started) / 1e9;

        expect($elapsedSeconds)->toBeGreaterThanOrEqual(0.8)->toBeLessThan(3.0);
        Exceptions::assertReported(fn (RedisException $exception): bool => $scheme !== 'tls'
            || $exception->getPrevious() instanceof ErrorException);
    } finally {
        $server->stop(0.1);
    }
})->with([
    'default read' => ['default', 'tcp'],
    'cache read' => ['cache', 'tcp'],
    'default TLS handshake' => ['default', 'tls'],
    'cache TLS handshake' => ['cache', 'tls'],
]);
