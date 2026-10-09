@extends('errors.layout')

@section('code', 'Ошибка 403')
@section('title', 'Доступ закрыт')
@section('message', 'У этой страницы другой владелец. Если вы ждали увидеть здесь свою презентацию, войдите в тот аккаунт, в котором её создавали.')

@section('actions')
<a class="button secondary" href="{{ url('/presentations') }}">Мои презентации</a>
<a class="button primary" href="{{ url('/') }}">На главную</a>
@endsection
