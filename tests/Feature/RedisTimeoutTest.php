<?php

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

/** @param list<string> $arguments */
function withRedisTimeoutServer(string $fixture, Closure $callback, array $arguments = []): void
{
    $server = new Process([PHP_BINARY, base_path("tests/Fixtures/{$fixture}"), ...$arguments]);
    $server->setTimeout(15);
    $server->start();

    try {
        expect($server->waitUntil(fn (string $type, string $output): bool => str_contains($output, "\n")))->toBeTrue();
        [, $port] = explode(':', trim($server->getOutput()));
        $callback($server, (int) $port);
    } finally {
        $server->stop(0.1);
    }
}

function configureRedisTimeoutConnection(string $connection, int $port, string $scheme = 'tcp'): void
{
    config(["database.redis.{$connection}" => [
        ...config("database.redis.{$connection}"),
        'url' => null,
        'scheme' => $scheme,
        'host' => '127.0.0.1',
        'port' => $port,
        'username' => null,
        'password' => null,
        'max_retries' => 0,
    ]]);
}

test('Redis stops waiting when a local server stalls connection setup', function (string $connection, string $scheme) {
    withRedisTimeoutServer('redis-silent-server.php', function (Process $server, int $port) use ($connection, $scheme) {
        configureRedisTimeoutConnection($connection, $port, $scheme);
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
    });
})->with([
    'default setup read' => ['default', 'tcp'],
    'cache setup read' => ['cache', 'tcp'],
    'default TLS handshake' => ['default', 'tls'],
    'cache TLS handshake' => ['cache', 'tls'],
]);

test('established Redis reads remain bounded through Laravel reconnection', function (string $connection, bool $recover) {
    withRedisTimeoutServer('redis-stalled-commands.php', function (Process $server, int $port) use ($connection, $recover) {
        configureRedisTimeoutConnection($connection, $port);
        expect(app('redis')->connection($connection)->ping())->toBeTrue();
        Exceptions::fake([RedisException::class]);
        Route::get('/__redis-command', fn () => app('redis')->connection($connection)->get('outage-probe'));
        $started = hrtime(true);

        $response = $this->get('/__redis-command');
        $elapsedSeconds = (hrtime(true) - $started) / 1e9;

        expect($elapsedSeconds)->toBeGreaterThanOrEqual($recover ? 0.8 : 1.8)->toBeLessThan(4.0);
        expect(substr_count($server->getOutput(), "GET\n"))->toBe(2);

        if ($recover) {
            $response->assertOk()->assertContent('recovered');
            Exceptions::assertNothingReported();
        } else {
            $response->assertServiceUnavailable()
                ->assertContent('Service temporarily unavailable. Please try again later.');
            Exceptions::assertReported(RedisException::class);
        }
    }, [$recover ? 'recover' : 'stall']);
})->with([
    'default persistent outage' => ['default', false],
    'cache persistent outage' => ['cache', false],
    'default recovery' => ['default', true],
    'cache recovery' => ['cache', true],
]);
