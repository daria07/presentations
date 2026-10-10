<x-mail::message>
# Отзыв о сервисе

{{ $body }}

<x-mail::panel>
**{{ $author->name }}** · {{ $author->email }}
Генераций на счету: {{ $author->credits }}, презентаций: {{ $author->presentations()->count() }}
</x-mail::panel>

Чтобы ответить, просто нажмите «Ответить» — письмо уйдёт автору отзыва.

<x-mail::button :url="route('admin.user', $author)">
Карточка в админке
</x-mail::button>
</x-mail::message>
