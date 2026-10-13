<?php

use App\Enums\PresentationStatus;
use App\Jobs\RenderPresentation;
use App\Models\Presentation;
use App\Models\User;
use App\Services\Deck\DeckRenderer;
use Illuminate\Support\Facades\Queue;

/*
   Слайд с источниками можно скрыть после генерации и вернуть обратно.
*/

function deckWithSources(): Presentation
{
    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'Пётр I',
        'outline' => ['title' => 'Пётр I', 'slides' => [
            ['layout' => 'title', 'heading' => 'Пётр I'],
            ['layout' => 'closing', 'heading' => 'Итоги'],
            ['layout' => 'sources', 'heading' => 'Источники', 'sources' => [
                ['title' => 'Википедия', 'url' => 'https://ru.wikipedia.org/wiki/X'],
            ]],
        ]],
    ]);
    // Готовая — значит, со статусом и файлом
    $presentation->forceFill(['status' => PresentationStatus::Ready, 'file_path' => 'presentations/1.pdf'])->save();

    return $presentation;
}

test('источники скрываются, перепечатываются и не попадают в вёрстку', function () {
    Queue::fake();
    $presentation = deckWithSources();

    $this->actingAs($presentation->user)
        ->post(route('presentations.sources', $presentation), ['visible' => false])
        ->assertRedirect();

    $presentation->refresh();

    expect($presentation->outline['slides'][2]['hidden'])->toBeTrue()
        ->and($presentation->status)->toBe(PresentationStatus::Generating)
        ->and(app(DeckRenderer::class)->html($presentation, forScreen: true))->not->toContain('ru.wikipedia.org');

    Queue::assertPushed(RenderPresentation::class);
});

test('скрытые источники возвращаются той же ручкой', function () {
    Queue::fake();
    $presentation = deckWithSources();
    $outline = $presentation->outline;
    $outline['slides'][2]['hidden'] = true;
    $presentation->update(['outline' => $outline]);

    $this->actingAs($presentation->user)
        ->post(route('presentations.sources', $presentation), ['visible' => true]);

    expect($presentation->refresh()->outline['slides'][2]['hidden'])->toBeFalse();
});

test('чужую презентацию не тронуть', function () {
    $presentation = deckWithSources();

    $this->actingAs(User::factory()->create())
        ->post(route('presentations.sources', $presentation), ['visible' => false])
        ->assertForbidden();
});
