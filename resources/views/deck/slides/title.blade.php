@use(App\Services\Deck\Motifs)

<div class="slide slide--cover @if (filled($motif ?? null)) has-motif @endif">
    @if (filled($motif ?? null))
        <div class="motif">{!! Motifs::svg($motif) !!}</div>
    @endif

    <div class="rule-accent" style="margin-bottom: 12mm;"></div>
    <h1>{{ $slide['heading'] }}</h1>
    @if (filled($slide['subheading'] ?? null))
        <p class="subheading" style="font-size: 15pt; margin-top: 7mm;">{{ $slide['subheading'] }}</p>
    @endif
</div>
