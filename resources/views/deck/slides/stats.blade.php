<div class="slide">
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
        @if (filled($slide['subheading'] ?? null))
            <p class="subheading">{{ $slide['subheading'] }}</p>
        @endif
    </div>
    <div class="body">
        <div class="stats">
            @foreach ($slide['stats'] ?? [] as $s)
                <div class="stat">
                    <div class="stat-value">{{ $s['value'] }}</div>
                    <div class="stat-label">{{ $s['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
