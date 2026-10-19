<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(config('fortify.home'));
});

test('пароль: от 8 символов и хотя бы одна цифра', function (string $password) {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'weak@example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
})->with([
    'без цифры' => 'password',
    'короче 8' => 'pass12',
]);
