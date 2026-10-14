<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('на лендинге тарифы из конфига и без служебного за 10 ₽', function () {
    $this->withoutVite();
    config(['billing.test_emails' => ['tester@example.com']]);

    $this->get(route('home'))->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->where('freeSlides', 8)
        ->where('packages', fn ($packages) => collect($packages)->pluck('key')->doesntContain('test')
            && collect($packages)->firstWhere('key', 'single')['amount'] === '149'));
});

test('у лендинга заголовок, описание и разметка для поиска прямо в HTML', function () {
    $this->withoutVite();

    $this->get(route('home'))
        ->assertSee('<title>Нейросеть для презентаций', false)
        ->assertSee('Первая — бесплатно', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('"priceCurrency":"RUB"', false);
});
