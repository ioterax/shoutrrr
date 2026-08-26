<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());

    config(['auth.email_verification.enabled' => true]);
});

test('sends verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('success');

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('resend transport is available for production verification emails', function () {
    config([
        'services.resend.key' => 're_test_key',
    ]);

    Mail::purge('resend');

    expect(Mail::mailer('resend')->getSymfonyTransport())
        ->toBeInstanceOf(ResendTransport::class);
});

test('does not send verification notification if email is verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});

test('does not send verification notification when mail delivery verification is disabled', function () {
    Notification::fake();
    config(['auth.email_verification.enabled' => false]);

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});
