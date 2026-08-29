<div class="slide">
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
        @if (filled($slide['subheading'] ?? null))
            <p class="subheading">{{ $slide['subheading'] }}</p>
        @endif
    </div>
    <div class="body">
        <div class="bullets">
            @foreach ($slide['bullets'] ?? [] as $n => $b)
                <div class="bullet">
                    <div class="bullet-mark">{{ $n + 1 }}</div>
                    <div>
                        <div class="bullet-title">{{ $b['title'] }}</div>
                        <div class="bullet-text">{{ $b['text'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
