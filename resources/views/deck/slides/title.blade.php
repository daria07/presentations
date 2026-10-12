{{-- Обложка без украшений: заголовок и без того занимает её целиком,
     а узор и круги начинали спорить с длинным названием.
     Класс slide--bare гасит декоративные ::before и ::after. --}}
@php($image = filled($slide['image']['src'] ?? null) ? $slide['image'] : null)

{{-- С иллюстрацией обложка становится фото во весь слайд с затемнением
     слева, под названием --}}
<div @class(['slide', 'slide--cover', 'slide--bare', 'slide--cover-photo' => $image])>
    @if ($image)
        @include('deck.partials.image', ['image' => $image])
    @endif
    <div class="rule-accent" style="margin-bottom: 12mm;"></div>
    <h1>{{ $slide['heading'] }}</h1>
    @if (filled($slide['subheading'] ?? null))
        <p class="subheading" style="font-size: 15pt; margin-top: 7mm;">{{ $slide['subheading'] }}</p>
    @endif
</div>
