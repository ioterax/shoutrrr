<?php

use Illuminate\Support\Env;

test('Redis connection budgets can be configured without weakening TLS', function (string $connection) {
    $environment = Env::getRepository();
    $values = [
        'REDIS_TIMEOUT' => '0.25',
        'REDIS_READ_TIMEOUT' => '0.5',
        'REDIS_MAX_RETRIES' => '0',
        'REDIS_SCHEME' => 'tls',
        'REDIS_TLS_CA_PATH' => '/test/private-ca.crt',
        'REDIS_TLS_VERIFY_PEER' => 'true',
        'REDIS_TLS_VERIFY_PEER_NAME' => 'true',
    ];
    $original = [];

    try {
        foreach ($values as $key => $value) {
            $original[$key] = $environment->get($key);
            $environment->clear($key);
            $environment->set($key, $value);
        }

        $configuration = (require config_path('database.php'))['redis'][$connection];

        expect($configuration['timeout'])->toBe(0.25)
            ->and($configuration['read_timeout'])->toBe(0.5)
            ->and((int) $configuration['max_retries'])->toBe(0)
            ->and($configuration['scheme'])->toBe('tls')
            ->and($configuration['context']['stream'])->toBe([
                'cafile' => '/test/private-ca.crt',
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]);
    } finally {
        foreach ($original as $key => $value) {
            $environment->clear($key);

            if ($value !== null) {
                $environment->set($key, $value);
            }
        }
    }
})->with(['default', 'cache']);

test('both Redis connections default to one-second connect and read limits', function (string $connection) {
    expect(config("database.redis.{$connection}.timeout"))->toBe(1.0)
        ->and(config("database.redis.{$connection}.read_timeout"))->toBe(1.0);
})->with(['default', 'cache']);
