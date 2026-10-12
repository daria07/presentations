{{--
    Иллюстрация слайда. $image['src'] — data URI, его готовит
    DeckRenderer::images(): картинка встроена в HTML, чтобы работать
    и в PDF (Chrome без доступа к сети), и в превью на сайте.
    Подпись обязательна: картинка выглядит как документ, а это
    фантазия нейросети.
--}}
@php($box = $image['box'] ?? null)
{{-- Рамка по пропорциям картинки (DeckRenderer::imageBox) — на обложке
     её нет: там картинка фоном на весь слайд --}}
<figure class="slide-image" aria-hidden="true"
    @if ($box && ($image['side'] ?? null) !== 'cover')
        style="top: {{ $box['top'] }}mm; left: {{ $box['left'] }}mm; width: {{ $box['width'] }}mm; height: {{ $box['height'] }}mm;"
    @endif
>
    <img src="{{ $image['src'] }}" alt="">
    <figcaption class="slide-image-note">Иллюстрация создана нейросетью</figcaption>
</figure>
