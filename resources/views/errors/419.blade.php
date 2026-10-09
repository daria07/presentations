@extends('errors.layout')

@section('code', 'Ошибка 419')
@section('title', 'Страница открыта слишком давно')
@section('message', 'Сессия истекла, пока вкладка была открыта. Обновите страницу и повторите последнее действие.')

@section('actions')
<a class="button primary" href="{{ url('/') }}">На главную</a>
<a class="button secondary" href="{{ url('/presentations') }}">Мои презентации</a>
@endsection
