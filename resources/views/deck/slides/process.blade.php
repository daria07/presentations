@use(App\Services\Deck\Icons)

<div class="slide">
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
        @if (filled($slide['subheading'] ?? null))
            <p class="subheading">{{ $slide['subheading'] }}</p>
        @endif
    </div>
    <div class="body body--center">
        <div class="process">
            @foreach (array_slice($slide['bullets'] ?? [], 0, 5) as $n => $b)
                <div class="step">
                    {{-- В этапах порядок важен, поэтому номер остаётся,
                         а иконка идёт дополнением --}}
                    @if (Icons::has($b['icon'] ?? null))
                        <div class="step-icon">{!! Icons::svg($b['icon'], '7mm') !!}</div>
                    @endif

                    <div class="step-num">{{ str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="step-title">{{ $b['title'] }}</div>
                    <div class="step-text">{{ $b['text'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
