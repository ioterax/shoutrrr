<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\MigrationManifest;
use Illuminate\Console\Command;

class AssertWebRelease extends Command
{
    protected $signature = 'production:assert-web-release {manifest : Approved migration manifest SHA-256}';

    protected $description = 'Check web-only migration compatibility and production users without changing the database.';

    public function handle(MigrationManifest $manifest): int
    {
        $expected = (string) $this->argument('manifest');

        if (! app()->isProduction() || ! preg_match('/\A[0-9a-f]{64}\z/', $expected)) {
            $this->error('A production environment and a valid migration manifest are required.');

            return self::FAILURE;
        }

        if (! hash_equals($expected, $manifest->hash())) {
            $this->error('The runtime migration manifest differs from the approved web release.');

            return self::FAILURE;
        }

        if ($this->call('migrate:status', ['--pending' => '1']) !== self::SUCCESS) {
            return self::FAILURE;
        }

        return $this->call('production:assert-no-demo-users');
    }
}
