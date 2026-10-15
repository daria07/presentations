<?php

use App\Enums\PresentationStatus;
use App\Jobs\GeneratePresentation;
use App\Jobs\IllustrateCover;
use App\Jobs\IllustratePresentation;
use App\Jobs\RenderPresentation;
use App\Models\ApiCall;
use App\Models\Presentation;
use App\Models\User;
use App\Services\Claude\PresentationPlanner;
use App\Services\Deck\DeckRenderer;
use App\Services\Deck\Illustrations;
use App\Services\Images\Illustrator;
use App\Services\Images\ImageClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
   Картинки к слайдам: рисуются отдельной задачей после структуры,
   все разом, со стилем темы. Ничто здесь не роняет генерацию.
*/

beforeEach(function () {
    Storage::fake('local');
    config([
        'deck.disk' => 'local',
        'services.images' => [
            'model' => 'gpt-image-test',
            'base_url' => 'https://gw.test',
            'key' => 'k',
            'quality' => 'medium',
            'price_input' => 15000,
            'price_output' => 90000,
        ],
    ]);
});

/** Крошечный настоящий PNG 1×1 */
function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

function deckWithScenes(): Presentation
{
    $user = User::factory()->create();

    $presentation = Presentation::create([
        'user_id' => $user->id,
        'topic' => 'Пётр I',
        'theme' => 'soft',
        'outline' => [
            'title' => 'Пётр I',
            'slides' => [
                ['layout' => 'title', 'heading' => 'Пётр I', 'image' => ['scene' => 'Neva at dawn', 'side' => 'cover']],
                ['layout' => 'bullets', 'heading' => 'Верфи', 'bullets' => [], 'image' => ['scene' => 'shipyard', 'side' => 'right']],
                ['layout' => 'stats', 'heading' => 'Цифры', 'stats' => []],
                ['layout' => 'quote', 'heading' => 'Цитата', 'image' => ['scene' => 'old map', 'side' => 'left', 'format' => 'portrait']],
            ],
        ],
    ]);
    $presentation->forceFill(['status' => PresentationStatus::Generating])->save();

    return $presentation;
}

test('рисует все картинки одним заходом, со стилем темы и нужным размером', function () {
    Http::fake(['gw.test/*' => Http::response([
        'data' => [['b64_json' => base64_encode(tinyPng())]],
        'usage' => ['input_tokens' => 70, 'output_tokens' => 1600],
    ])]);

    $presentation = deckWithScenes();

    app(Illustrator::class)->illustrate($presentation);

    $slides = $presentation->refresh()->outline['slides'];

    foreach ([0, 1, 3] as $i) {
        expect($slides[$i]['image']['path'])->toStartWith(DeckRenderer::imageDir($presentation).'/')
            ->and(Storage::disk('local')->exists($slides[$i]['image']['path']))->toBeTrue();
    }

    expect($slides[2])->not->toHaveKey('image');

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $r) => $r['size'] === '1536x1024' && str_contains($r['prompt'], 'Neva at dawn')
        && str_contains($r['prompt'], 'watercolor') && str_contains($r['prompt'], 'no text'));
    Http::assertSent(fn (Request $r) => $r['size'] === '1024x1024' && str_contains($r['prompt'], 'shipyard'));
    Http::assertSent(fn (Request $r) => $r['size'] === '1024x1536' && str_contains($r['prompt'], 'old map')
        && str_contains($r['prompt'], 'Vertical composition'));

    // Расход записан: 3 картинки × (70 × 0.015 + 1600 × 0.09) сотых цента
    expect(ApiCall::where('purpose', 'image')->count())->toBe(3)
        ->and(ApiCall::where('purpose', 'image')->sum('cost'))->toEqual(3 * 145);
});

test('обрыв соединения повторяется, отказ модели — нет, и слайд остаётся без картинки', function () {
    $calls = ['shipyard' => 0, 'old map' => 0, 'Neva' => 0];

    Http::fake(function (Request $r) use (&$calls) {
        $key = collect(array_keys($calls))->first(fn ($k) => str_contains($r['prompt'], $k));
        $calls[$key]++;

        return match ($key) {
            // Первый раз рвётся, второй — ок
            'shipyard' => $calls[$key] === 1
                ? Http::failedConnection('cURL error 56: reset')
                : Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]]),
            // Неверный запрос — от повтора не изменится
            'old map' => Http::response(['error' => ['message' => 'invalid size']], 400),
            default => Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]]),
        };
    });

    $presentation = deckWithScenes();
    app(Illustrator::class)->illustrate($presentation);

    $slides = $presentation->refresh()->outline['slides'];

    expect($calls)->toBe(['shipyard' => 2, 'old map' => 1, 'Neva' => 1])
        ->and($slides[1]['image']['path'])->not->toBeNull()
        ->and($slides[3]['image']['failed'])->toBeTrue()
        // Больше рисовать нечего: отказ не повторяется при повторе задачи
        ->and(Illustrator::pending(['slides' => $slides]))->toBeFalse();
});

test('ложный отказ по контент-политике повторяется с упрощённой сценой по заголовку', function () {
    $prompts = [];

    Http::fake(function (Request $r) use (&$prompts) {
        $prompts[] = $r['prompt'];

        // Шлюз отказывает на «shipyard», а на заголовок слайда — рисует
        return str_contains($r['prompt'], 'shipyard')
            ? Http::response(['error' => ['message' => '非常抱歉, the 提示可能违反了我们 内容政策.']], 400)
            : Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]]);
    });

    $presentation = deckWithScenes();
    app(Illustrator::class)->illustrate($presentation);

    $slides = $presentation->refresh()->outline['slides'];

    expect($slides[1]['image']['path'])->not->toBeNull()
        ->and(collect($prompts)->contains(fn ($p) => str_starts_with($p, 'Верфи.')))->toBeTrue();
});

test('генерация отдаёт картинки отдельной задаче, когда они есть и включены', function () {
    Queue::fake();
    $presentation = deckWithScenes();

    (new GeneratePresentation($presentation, spent: 'credit'))->handle(
        Mockery::mock(PresentationPlanner::class),
        app(DeckRenderer::class),
    );

    Queue::assertPushed(IllustratePresentation::class, fn ($job) => $job->spent === 'credit');
    expect($presentation->refresh()->status)->toBe(PresentationStatus::Generating);
});

test('без IMAGE_MODEL картинок нет и отдельной задачи тоже', function () {
    config(['services.images.model' => null]);

    expect(Illustrator::enabled())->toBeFalse();
});

test('чужой путь к картинке в структуре не попадает в HTML', function () {
    Storage::disk('local')->put('secret.txt', 'SECRET');
    $presentation = deckWithScenes();

    $outline = $presentation->outline;
    $outline['slides'][1]['image']['path'] = DeckRenderer::imageDir($presentation).'/../../../secret.txt';
    $presentation->outline = $outline;

    $html = app(DeckRenderer::class)->html($presentation);

    expect($html)->not->toContain(base64_encode('SECRET'))
        // Класс есть в стилях, но слайд не должен его получить
        ->and($html)->not->toContain('class="slide slide--image-right"');
});

test('удаление презентации забирает папку с картинками', function () {
    $presentation = deckWithScenes();
    $path = DeckRenderer::imageDir($presentation).'/1-abc.jpg';
    Storage::disk('local')->put($path, 'x');

    $presentation->delete();

    expect(Storage::disk('local')->exists($path))->toBeFalse();
});

test('герой презентации называется в каждом запросе картинки', function () {
    Http::fake(['gw.test/*' => Http::response([
        'data' => [['b64_json' => base64_encode(tinyPng())]],
        'usage' => ['input_tokens' => 70, 'output_tokens' => 1600],
    ])]);

    $presentation = deckWithScenes();
    $presentation->update(['outline' => [...$presentation->outline, 'image_subject' => 'white standard poodle with a curly coat']]);

    app(Illustrator::class)->illustrate($presentation);

    $prompts = collect(Http::recorded())->map(fn ($pair) => $pair[0]['prompt'] ?? '')->filter();

    expect($prompts)->toHaveCount(3)
        ->and($prompts->every(fn ($p) => str_contains($p, 'The presentation is about white standard poodle with a curly coat')))->toBeTrue();
});

test('картинка из потокового ответа: отметки о ходе работы, потом результат', function () {
    $png = base64_encode(tinyPng());
    $stream = 'data: {"object": "image.generation.chunk", "index": 1, "progress_text": ""}'."\n\n"
        .'data: {"object": "image.generation.chunk", "index": 1, "progress_text": ""}'."\n\n"
        .'data: {"object": "image.generation.result", "data": [{"b64_json": "'.$png.'"}], "usage": {"input_tokens": 70, "output_tokens": 1600}}'."\n\n"
        ."data: [DONE]\n\n";

    Http::fake(['gw.test/*' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream'])]);

    $presentation = deckWithScenes();

    app(Illustrator::class)->illustrate($presentation);

    $slides = $presentation->refresh()->outline['slides'];

    expect(Storage::disk('local')->exists($slides[1]['image']['path']))->toBeTrue()
        ->and(ApiCall::where('purpose', 'image')->sum('output_tokens'))->toEqual(4800);

    Http::assertSent(fn (Request $r) => ($r['stream'] ?? false) === true);
});

test('поток в формате OpenAI: набросок пропускаем, берём готовую картинку', function () {
    $png = base64_encode(tinyPng());
    $stream = "event: image_generation.partial_image\n"
        .'data: {"type":"image_generation.partial_image","b64_json":"bm90IGEgcG5n","partial_image_index":0}'."\n\n"
        ."event: image_generation.completed\n"
        .'data: {"type":"image_generation.completed","b64_json":"'.$png.'","usage":{"input_tokens":70,"output_tokens":1372}}'."\n\n";

    Http::fake(['gw.test/*' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream'])]);

    $result = ImageClient::result(Http::post('https://gw.test/v1/images/generations'));

    expect($result['b64'])->toBe($png)
        ->and($result['usage']['output_tokens'])->toBe(1372)
        ->and($result['error'])->toBeNull();
});

test('готовая картинка, порезанная шлюзом на несколько строк data, не теряется', function () {
    $png = base64_encode(tinyPng());
    $json = '{"type":"image_generation.completed","b64_json":"'.$png.'","usage":{"input_tokens":70,"output_tokens":1372}}';
    // Режем JSON посреди base64 — каждая строка по отдельности не JSON
    $parts = str_split($json, 40);

    $stream = ": PING\n\n"
        ."event: image_generation.completed\n"
        .implode('', array_map(fn ($p) => 'data: '.$p."\n", $parts))
        ."\n";

    Http::fake(['gw.test/*' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream'])]);

    $result = ImageClient::result(Http::post('https://gw.test/v1/images/generations'));

    expect($result['b64'])->toBe($png)
        ->and($result['usage']['output_tokens'])->toBe(1372);
});

test('поток без картинки (оборванный) повторяется', function () {
    $calls = 0;
    $png = base64_encode(tinyPng());

    Http::fake(function () use (&$calls, $png) {
        $calls++;

        return $calls === 1
            ? Http::response("event: image_generation.completed\ndata: {\"type\":\"image_generation.completed\",\"b64_json\":\"iVBO", 200)
            : Http::response(['data' => [['b64_json' => $png]]]);
    });

    $results = app(ImageClient::class)->generateMany(['0' => ['prompt' => 'кот', 'size' => '1024x1024']]);

    expect($calls)->toBe(2)
        ->and($results['0'])->not->toBeNull();
});

test('после конспекта структура собирается следующей задачей', function () {
    Queue::fake();

    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'Переломы',
        'source_text' => str_repeat('Текст про переломы. ', 20),
    ]);

    $planner = Mockery::mock(PresentationPlanner::class);
    $planner->shouldReceive('buildBrief')->once()->andReturn(['thesis' => 'x', 'points' => []]);
    $planner->shouldNotReceive('buildOutline');

    (new GeneratePresentation($presentation, spent: 'trial'))->handle($planner, app(DeckRenderer::class));

    expect($presentation->refresh()->brief)->toBe(['thesis' => 'x', 'points' => []]);
    Queue::assertPushed(GeneratePresentation::class, fn ($job) => $job->spent === 'trial');
});

test('если шлюз прислал ссылку вместо картинки, картинку скачиваем', function () {
    $stream = "event: image_generation.completed\n"
        .'data: {"type":"image_generation.completed","url":"https://images.test/x.png?sig=1"}'."\n\n";

    Http::fake([
        'gw.test/*' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream']),
        'images.test/*' => Http::response(tinyPng(), 200, ['Content-Type' => 'image/png']),
    ]);

    $presentation = deckWithScenes();

    app(Illustrator::class)->illustrate($presentation);

    $slides = $presentation->refresh()->outline['slides'];

    expect(Storage::disk('local')->exists($slides[0]['image']['path']))->toBeTrue();
});

test('после конспекта обложка рисуется отдельной задачей, параллельно со структурой', function () {
    Queue::fake();

    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'Пудель',
        'source_text' => str_repeat('Текст про пуделей. ', 20),
    ]);

    $planner = Mockery::mock(PresentationPlanner::class);
    $planner->shouldReceive('buildBrief')->once()->andReturn([
        'thesis' => 'x', 'points' => [],
        'image_subject' => 'white standard poodle', 'cover_scene' => 'poodle on a lawn',
    ]);

    (new GeneratePresentation($presentation))->handle($planner, app(DeckRenderer::class));

    expect($presentation->refresh()->cover_image)->toBe(['pending' => true]);
    Queue::assertPushed(IllustrateCover::class);
    Queue::assertPushed(GeneratePresentation::class);
});

test('готовая обложка подставляется, а не рисуется второй раз', function () {
    Notification::fake();

    $requests = 0;
    Http::fake(function () use (&$requests) {
        $requests++;

        return Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]]);
    });

    $presentation = deckWithScenes();
    Storage::disk('local')->put('presentations/'.$presentation->id.'/images/0-ready.jpg', tinyPng());
    $presentation->update(['cover_image' => ['path' => 'presentations/'.$presentation->id.'/images/0-ready.jpg']]);

    $renderer = Mockery::mock(DeckRenderer::class);
    $renderer->shouldReceive('pdf')->andReturn('presentations/x.pdf');

    (new IllustratePresentation($presentation))->handle(app(Illustrator::class), $renderer);

    $slides = $presentation->refresh()->outline['slides'];

    expect($requests)->toBe(2)
        ->and($slides[0]['image']['path'])->toBe('presentations/'.$presentation->id.'/images/0-ready.jpg')
        ->and($presentation->status)->toBe(PresentationStatus::Ready);
});

test('images:redraw дорисовывает несостоявшиеся картинки только оплаченным', function () {
    Bus::fake([RenderPresentation::class]);
    Http::fake(['gw.test/*' => Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]])]);

    $make = function (string $paidWith): Presentation {
        $p = deckWithScenes();
        $outline = $p->outline;
        $outline['slides'][1]['image']['failed'] = true;
        $p->forceFill([
            'outline' => $outline,
            'paid_with' => $paidWith,
            'status' => PresentationStatus::Ready,
            'file_path' => 'presentations/x.pdf',
        ])->save();

        return $p;
    };

    $paid = $make('credit');
    $trial = $make('trial');

    $this->artisan('images:redraw', ['--force' => true])->assertSuccessful();

    expect($paid->refresh()->outline['slides'][1]['image']['path'])->not->toBeNull()
        ->and($trial->refresh()->outline['slides'][1]['image']['failed'])->toBeTrue();

    Bus::assertDispatchedSync(RenderPresentation::class, 1);
});

test('недостающие картинки добираются на подходящие слайды, уже назначенные остаются', function () {
    $slides = [
        ['layout' => 'title', 'heading' => 'Соловьёв', 'image' => ['scene' => 'ship', 'side' => 'cover']],
        ['layout' => 'bullets', 'heading' => 'Путь', 'bullets' => [[], [], []]],
        ['layout' => 'bignumber', 'heading' => 'АС-31', 'image' => ['scene' => 'submarine', 'side' => 'left', 'path' => 'x.jpg']],
        ['layout' => 'bullets', 'heading' => 'Авария', 'bullets' => [[], [], []]],
        ['layout' => 'process', 'heading' => 'Решение', 'bullets' => [[], [], []]],
        ['layout' => 'sources', 'heading' => 'Источники'],
    ];

    $result = Illustrations::topUp($slides, 8);

    expect($result[2]['image']['path'])->toBe('x.jpg')
        ->and(isset($result[1]['image']) || isset($result[3]['image']))->toBeTrue()
        ->and(count(array_filter($result, fn ($s) => isset($s['image']) && $s['image']['side'] !== 'cover')))->toBe(2)
        ->and(isset($result[5]['image']))->toBeFalse();
});

test('для grok запрос короткий, без служебных фраз, которые он рисует буквами', function () {
    config(['services.images.model' => 'grok-imagine-image']);
    $prompts = [];

    Http::fake(function (Request $r) use (&$prompts) {
        $prompts[] = $r['prompt'];

        return Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]]);
    });

    app(Illustrator::class)->illustrate(deckWithScenes());

    expect($prompts)->not->toBeEmpty();

    foreach ($prompts as $prompt) {
        expect($prompt)->not->toContain('The presentation is about')
            ->and($prompt)->not->toContain('Show the main subject')
            ->and($prompt)->toContain('no text');
    }
});

test('images:redraw --fresh перерисовывает и удачные картинки, но только поимённо', function () {
    Bus::fake([RenderPresentation::class]);
    Http::fake(['gw.test/*' => Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]])]);

    $p = deckWithScenes();
    $outline = $p->outline;
    $outline['slides'][1]['image']['path'] = 'presentations/old.jpg';
    $p->forceFill([
        'outline' => $outline,
        'paid_with' => 'credit',
        'status' => PresentationStatus::Ready,
        'file_path' => 'presentations/x.pdf',
    ])->save();

    $this->artisan('images:redraw', ['--fresh' => true, '--force' => true])->assertFailed();

    $this->artisan('images:redraw', ['--id' => [$p->id], '--fresh' => true, '--force' => true])->assertSuccessful();

    expect($p->refresh()->outline['slides'][1]['image']['path'])->not->toBe('presentations/old.jpg');
});

test('IMAGE_STREAM=false — картинка без потока', function () {
    config(['services.images.stream' => false]);
    $bodies = [];

    Http::fake(function (Request $r) use (&$bodies) {
        $bodies[] = $r->data();

        return Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]]);
    });

    $results = app(ImageClient::class)->generateMany(['0' => ['prompt' => 'кот', 'size' => '1024x1024']]);

    expect($results['0'])->not->toBeNull()
        ->and($bodies[0])->not->toHaveKey('stream')
        ->and($bodies[0])->not->toHaveKey('partial_images');
});

test('502 у шлюза повторяется, а не сдаётся сразу', function () {
    $calls = 0;

    Http::fake(function () use (&$calls) {
        $calls++;

        return $calls === 1
            ? Http::response(['error' => ['message' => 'temporarily unavailable']], 502)
            : Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]]);
    });

    $results = app(ImageClient::class)->generateMany(['0' => ['prompt' => 'кот', 'size' => '1024x1024']]);

    expect($calls)->toBe(2)->and($results['0'])->not->toBeNull();
});

test('не вышло у основного шлюза — рисует запасной, с коротким запросом для grok', function () {
    config(['services.images_fallback' => [
        'model' => 'grok-imagine-image',
        'base_url' => 'https://backup.test',
        'key' => 'k2',
        'stream' => null,
        'compact_prompt' => null,
    ]]);
    $backupPrompts = [];

    Http::fake(function (Request $r) use (&$backupPrompts) {
        if (str_contains($r->url(), 'backup.test')) {
            $backupPrompts[] = $r['prompt'];

            return Http::response(['data' => [['b64_json' => base64_encode(tinyPng())]]]);
        }

        return Http::response(['error' => ['message' => 'down']], 503);
    });

    $results = app(ImageClient::class)->generateMany([
        '0' => ['prompt' => 'Long prompt. The presentation is about cats.', 'compact' => 'cat, no text', 'size' => '1024x1024'],
    ]);

    expect($results['0'])->not->toBeNull()
        ->and($backupPrompts)->toBe(['cat, no text']);
});
