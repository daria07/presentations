<div class="slide slide--cover">
    <div class="rule-accent" style="margin-bottom: 12mm;"></div>
    <h1>{{ $slide['heading'] }}</h1>
    @if (filled($slide['subheading'] ?? null))
        <p class="subheading" style="font-size: 15pt; margin-top: 7mm;">{{ $slide['subheading'] }}</p>
    @endif
</div>
