<?php

namespace App\Support;

use ErrorException;
use Illuminate\Redis\Connectors\PhpRedisConnector as BasePhpRedisConnector;
use Override;
use RedisException;

class PhpRedisConnector extends BasePhpRedisConnector
{
    /** @param array<string, mixed> $config */
    #[Override]
    protected function establishConnection(mixed $client, array $config): void
    {
        try {
            parent::establishConnection($client, $config);
        } catch (ErrorException $exception) {
            throw new RedisException('Redis connection failed.', previous: $exception);
        }
    }
}
