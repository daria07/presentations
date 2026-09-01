<div class="slide">
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
        @if (filled($slide['subheading'] ?? null))
            <p class="subheading">{{ $slide['subheading'] }}</p>
        @endif
    </div>
    <div class="body body--center">
        <div class="matrix">
            @foreach (array_slice($slide['bullets'] ?? [], 0, 4) as $b)
                <div class="cell">
                    <div class="cell-title">{{ $b['title'] }}</div>
                    <div class="cell-text">{{ $b['text'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
