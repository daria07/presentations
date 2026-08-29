<div class="slide slide--cover">
    <div class="eyebrow">Итог</div>
    <h1 style="font-size: 38pt;">{{ $slide['heading'] }}</h1>
    @if (filled($slide['subheading'] ?? null))
        <p class="subheading" style="font-size: 14pt; margin-top: 7mm;">{{ $slide['subheading'] }}</p>
    @endif
    @if (! empty($slide['bullets']))
        <div style="display:flex; gap:10mm; margin-top:12mm;">
            @foreach ($slide['bullets'] as $b)
                <div style="flex:1;">
                    <div style="font-weight:700; font-size:12.5pt; margin-bottom:2mm;">{{ $b['title'] }}</div>
                    <div style="font-size:11pt; line-height:1.45; color:rgba(255,255,255,.7);">{{ $b['text'] }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
