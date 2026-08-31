<div class="slide">
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
        @if (filled($slide['subheading'] ?? null))
            <p class="subheading">{{ $slide['subheading'] }}</p>
        @endif
    </div>
    <div class="body body--center">
        <div class="timeline">
            @foreach ($slide['stats'] ?? [] as $s)
                <div class="tl-item">
                    <div class="tl-dot"></div>
                    <div class="tl-stem"></div>
                    <div class="tl-value">{{ $s['value'] }}</div>
                    <div class="tl-label">{{ $s['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
