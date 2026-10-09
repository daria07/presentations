@extends('errors.layout')

@section('code', 'Ошибка 404')
@section('title', 'Такой страницы нет')
@section('message', 'Возможно, ссылка устарела или в адресе опечатка. Начните с главной — или загляните в свои презентации.')

@section('actions')
<a class="button primary" href="{{ url('/') }}">На главную</a>
<a class="button secondary" href="{{ url('/presentations') }}">Мои презентации</a>
@endsection
