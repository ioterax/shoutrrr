<?php

use App\Models\User;

beforeEach(function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
});

it('passes when the production database contains no bundled development users', function (): void {
    User::factory()->create(['email' => 'owner@ioterax.com']);

    $this->artisan('production:assert-no-demo-users')
        ->expectsOutput('Production database contains no bundled development users.')
        ->assertSuccessful();
});

it('blocks deployment when a bundled development user is present', function (string $email): void {
    User::factory()->create(['email' => $email]);

    $this->artisan('production:assert-no-demo-users')
        ->expectsOutput('Bundled development users are present; production deployment is blocked.')
        ->assertFailed();
})->with([
    'primary development user' => 'test@example.com',
    'secondary development user' => 'test2@example.com',
]);

it('cannot be used outside production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');

    $this->artisan('production:assert-no-demo-users')
        ->expectsOutput('The demo-user deployment gate may run only in production.')
        ->assertFailed();
});
