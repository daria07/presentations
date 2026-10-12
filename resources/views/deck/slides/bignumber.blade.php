@php($stat = ($slide['stats'] ?? [])[0] ?? null)

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
            <div class="bignum-value">{{ $stat['value'] ?? '—' }}</div>

            @if (filled($stat['label'] ?? null))
                <div class="bignum-label">{{ $stat['label'] }}</div>
            @endif

            @if (filled($slide['subheading'] ?? null))
                <div class="bignum-note">{{ $slide['subheading'] }}</div>
            @endif
        </div>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
