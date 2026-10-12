@php($image = filled($slide['image']['src'] ?? null) ? $slide['image'] : null)
@php($side = ($slide['image']['side'] ?? 'right') === 'left' ? 'left' : 'right')

<div @class(['slide', "slide--image-{$side}" => $image])>
    @if ($image)
        @include('deck.partials.image', ['image' => $image])
    @endif
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
    </div>
    <div class="body body--center">
        <div class="quote-mark">«</div>
        <div class="quote-text">{{ $slide['quote']['text'] ?? '' }}</div>
        @if (filled($slide['quote']['author'] ?? null))
            <div class="quote-author">— {{ $slide['quote']['author'] }}</div>
        @endif
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
