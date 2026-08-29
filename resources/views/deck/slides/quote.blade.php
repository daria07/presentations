<div class="slide">
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
    </div>
    <div class="body">
        <div class="quote-mark">«</div>
        <div class="quote-text">{{ $slide['quote']['text'] ?? '' }}</div>
        @if (filled($slide['quote']['author'] ?? null))
            <div class="quote-author">— {{ $slide['quote']['author'] }}</div>
        @endif
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
