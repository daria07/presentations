@extends('errors.layout')

@section('code', 'Ошибка 429')
@section('title', 'Слишком много запросов подряд')
@section('message', 'Мы ненадолго придержали действия с этого аккаунта, чтобы сервис оставался быстрым для всех. Подождите минуту и повторите — ничего не потерялось.')

@section('actions')
<a class="button secondary" href="{{ url('/presentations') }}">Мои презентации</a>
<a class="button primary" href="{{ url('/') }}">На главную</a>
@endsection
