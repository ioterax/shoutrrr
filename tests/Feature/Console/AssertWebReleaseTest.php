<?php

use App\Models\User;
use App\Support\MigrationManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
});

it('accepts the applied manifest and reads the database without writes', function (): void {
    User::factory()->create(['email' => 'owner@ioterax.com']);
    DB::enableQueryLog();

    $this->artisan('production:assert-web-release', ['manifest' => app(MigrationManifest::class)->hash()])
        ->expectsOutput('Production database contains no bundled development users.')
        ->assertSuccessful();

    foreach (DB::getQueryLog() as $query) {
        expect(strtolower(ltrim($query['query'])))->toStartWith('select');
    }
});

it('rejects invalid or different migration manifests before querying the database', function (string $manifest): void {
    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->artisan('production:assert-web-release', ['manifest' => $manifest])->assertFailed();

    expect(DB::getQueryLog())->toBeEmpty();
})->with(['invalid', '', str_repeat('0', 64)]);

it('rejects pending migrations without applying them', function (): void {
    $migration = DB::table('migrations')->value('migration');
    DB::table('migrations')->where('migration', $migration)->delete();
    $before = DB::table('migrations')->count();

    $this->artisan('production:assert-web-release', ['manifest' => app(MigrationManifest::class)->hash()])
        ->assertFailed();

    expect(DB::table('migrations')->count())->toBe($before)
        ->and(DB::table('migrations')->where('migration', $migration)->exists())->toBeFalse();
});

it('rejects a missing migration repository without creating it', function (): void {
    Schema::rename('migrations', 'saved_migrations');

    try {
        $this->artisan('production:assert-web-release', ['manifest' => app(MigrationManifest::class)->hash()])
            ->assertFailed();

        expect(Schema::hasTable('migrations'))->toBeFalse();
    } finally {
        Schema::rename('saved_migrations', 'migrations');
    }
});

it('keeps the production demo-user gate mandatory', function (): void {
    User::factory()->create(['email' => 'test@example.com']);

    $this->artisan('production:assert-web-release', ['manifest' => app(MigrationManifest::class)->hash()])
        ->expectsOutput('Bundled development users are present; production deployment is blocked.')
        ->assertFailed();
});

it('rejects non-production startup checks', function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');

    $this->artisan('production:assert-web-release', ['manifest' => app(MigrationManifest::class)->hash()])
        ->assertFailed();
});
