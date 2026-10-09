@extends('errors.layout')

@section('code', 'Ошибка 503')
@section('title', 'Идут технические работы')
@section('message', 'Сервис ненадолго недоступен — обновляемся. Обычно это занимает несколько минут.')

@section('actions')
<a class="button primary" href="{{ url('/') }}">На главную</a>
@endsection
