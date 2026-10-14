@php($stat = ($slide['stats'] ?? [])[0] ?? null)

{{-- Кегль от длины значения: «72%» держит весь слайд, а «Февраль 1920»
     в том же кегле переносилось на две строки, и строки наезжали друг
     на друга. Длинным значениям — ступенью мельче. --}}
@php($length = mb_strlen(trim((string) ($stat['value'] ?? ''))))
@php($size = match (true) {
    $length <= 5 => null,
    $length <= 8 => 'md',
    $length <= 12 => 'sm',
    default => 'xs',
})

@php($image = filled($slide['image']['src'] ?? null) ? $slide['image'] : null)
@php($side = ($slide['image']['side'] ?? 'right') === 'left' ? 'left' : 'right')

<div @class(['slide', "slide--image-{$side}" => $image])>
    @if ($image)
        @include('deck.partials.image', ['image' => $image])
    @endif
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
    </div>
    <div class="body body--center">
        <div class="bignum">
            <div @class(['bignum-value', "bignum-value--{$size}" => $size])>{{ $stat['value'] ?? '—' }}</div>

            @if (filled($stat['label'] ?? null))
                <div class="bignum-label">{{ $stat['label'] }}</div>
            @endif

            {{-- Подзаголовок, повторяющий подпись к числу, не выводим:
                 у уже готовых презентаций модель иногда писала одно и то же --}}
            @if (filled($slide['subheading'] ?? null)
                && mb_strtolower(trim($slide['subheading'], ' .')) !== mb_strtolower(trim((string) ($stat['label'] ?? ''), ' .')))
                <div class="bignum-note">{{ $slide['subheading'] }}</div>
            @endif
        </div>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
