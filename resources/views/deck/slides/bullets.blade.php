@use(App\Services\Deck\Icons)

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
                    {{-- Иконка говорит о смысле, номер — только о порядке.
                         Если модель подобрала подходящую, она полезнее. --}}
                    @if (Icons::has($b['icon'] ?? null))
                        <div class="bullet-mark bullet-mark--icon">
                            {!! Icons::svg($b['icon'], '4.4mm') !!}
                        </div>
                    @else
                        <div class="bullet-mark">{{ $n + 1 }}</div>
                    @endif

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
