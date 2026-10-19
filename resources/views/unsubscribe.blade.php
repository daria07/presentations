@extends('errors.layout')

@section('code', 'Письма о новостях')
@section('title', $done ? 'Вы отписались' : 'Отписаться от писем?')
@section('message', $done
    ? 'Больше не будем присылать письма о новостях сервиса. Письма о ваших презентациях и оплатах приходить будут — без них сервисом не пользоваться.'
    : 'Мы иногда пишем о том, что нового в Слайдуше. Если такие письма не нужны — нажмите кнопку.')

@section('actions')
    @if ($done)
        <a class="button primary" href="{{ url('/') }}">На главную</a>
    @else
        <form method="POST" action="{{ $action }}" style="margin:0">
            <button type="submit" class="button primary"
                style="cursor:pointer; border:0; font:inherit; font-weight:600; padding:12px 22px; border-radius:10px; background:var(--brand); color:#fff;">
                Отписаться
            </button>
        </form>
    @endif
@endsection
