<div class="slide">
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
        @if (filled($slide['subheading'] ?? null))
            <p class="subheading">{{ $slide['subheading'] }}</p>
        @endif
    </div>
    <div class="body body--center">
        <div class="compare">
            @foreach (array_slice($slide['bullets'] ?? [], 0, 2) as $b)
                <div class="compare-col">
                    <div class="compare-title">{{ $b['title'] }}</div>
                    <div class="compare-text">{{ $b['text'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
