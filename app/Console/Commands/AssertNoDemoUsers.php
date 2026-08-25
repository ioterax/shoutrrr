<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AssertNoDemoUsers extends Command
{
    /**
     * @var list<string>
     */
    private const array DEMO_EMAILS = [
        'test@example.com',
        'test2@example.com',
    ];

    protected $signature = 'production:assert-no-demo-users';

    protected $description = 'Fail when bundled development users are present in the production database.';

    public function handle(): int
    {
        if (! app()->isProduction()) {
            $this->error('The demo-user deployment gate may run only in production.');

            return self::FAILURE;
        }

        if (User::query()->whereIn('email', self::DEMO_EMAILS)->exists()) {
            $this->error('Bundled development users are present; production deployment is blocked.');

            return self::FAILURE;
        }

        $this->info('Production database contains no bundled development users.');

        return self::SUCCESS;
    }
}
