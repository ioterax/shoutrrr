<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

test('Redis outages bypass shared error data and hide internal details', function (string $path, array $headers, bool $json) {
    config(['app.debug' => true]);

    $this->mock(HandleInertiaRequests::class)->shouldNotReceive('share');
    Route::get($path, fn () => throw new RedisException('private-redis-host password=not-for-the-response'));

    $response = $this->get($path, $headers)
        ->assertServiceUnavailable()
        ->assertHeader('Retry-After', '60')
        ->assertHeaderMissing('X-Inertia')
        ->assertDontSee('private-redis-host')
        ->assertDontSee('password=');

    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();

    if ($json) {
        $response->assertExactJson(['message' => 'Service temporarily unavailable. Please try again later.']);
    } else {
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertContent('Service temporarily unavailable. Please try again later.');
    }
})->with([
    'browser' => ['/__redis-outage', ['Accept' => 'text/html'], false],
    'inertia' => ['/__redis-outage', ['Accept' => 'text/html', 'X-Inertia' => 'true'], false],
    'JSON' => ['/__redis-outage', ['Accept' => 'application/json'], true],
    'API without Accept' => ['/api/__redis-outage', [], true],
]);

test('a Redis session outage does not run the route or save the failed session', function () {
    $handler = Mockery::mock(SessionHandlerInterface::class);
    $handler->shouldReceive('read')->once()->andThrow(new RedisException('unavailable session store'));
    $handler->shouldNotReceive('write');
    $handler->shouldNotReceive('gc');

    app('session')->extend('outage-test', fn () => $handler);
    config(['session.driver' => 'outage-test']);

    $this->mock(HandleInertiaRequests::class)->shouldNotReceive('share');
    $executed = false;
    Route::middleware('web')->get('/__redis-session', function () use (&$executed) {
        $executed = true;

        return response('unexpected');
    });

    $this->get('/__redis-session')->assertServiceUnavailable();

    expect($executed)->toBeFalse();
});

test('a Redis outage while rendering a missing page does not retry shared cache data', function () {
    Cache::shouldReceive('rememberForever')
        ->once()
        ->with('instance_settings', Mockery::type(Closure::class))
        ->andThrow(new RedisException('unavailable shared settings'));

    $this->get('/__missing-during-redis-outage')
        ->assertServiceUnavailable()
        ->assertContent('Service temporarily unavailable. Please try again later.');
});

test('requests recover after a transient Redis outage in the same application', function () {
    $unavailable = true;
    Route::get('/__redis-recovery', function () use (&$unavailable) {
        if ($unavailable) {
            throw new RedisException('transient failure');
        }

        return response('recovered');
    });

    $this->get('/__redis-recovery')->assertServiceUnavailable();
    $unavailable = false;
    $this->get('/__redis-recovery')->assertOk()->assertContent('recovered');
});

test('Redis exceptions remain reportable outside HTTP rendering', function () {
    expect(app(ExceptionHandler::class)->shouldReport(new RedisException('unavailable')))->toBeTrue();
});
