@extends('errors.layout')

@section('code', 'Ошибка 500')
@section('title', 'Что-то сломалось на нашей стороне')
@section('message', 'Это не из-за ваших действий. Мы уже видим ошибку в журнале. Попробуйте обновить страницу через пару минут — списанные генерации при сбое возвращаются.')

@section('actions')
<a class="button primary" href="{{ url('/') }}">На главную</a>
<a class="button secondary" href="{{ url('/presentations') }}">Мои презентации</a>
@endsection
