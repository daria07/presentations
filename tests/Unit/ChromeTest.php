<?php

use App\Services\Deck\Chrome;
use Spatie\Browsershot\Browsershot;
use Tests\TestCase;

uses(TestCase::class);

/*
   Chrome печатает PDF без песочницы, поэтому сеть и скрипты ему
   выключены. Тест ловит случай, когда настройку забудут при новом
   месте печати или потеряют при рефакторинге.
*/
test('Chrome для печати без сети и без скриптов', function () {
    $command = Chrome::harden(Browsershot::html('<p>x</p>'))->createPdfCommand();

    expect($command['options']['disableJavascript'] ?? false)->toBeTrue()
        ->and($command['options']['blockUrls'] ?? [])->toContain('http://', 'https://');
});
