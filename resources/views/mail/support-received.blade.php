<x-mail::message>
# Сообщение в чате

{{ $message->body }}

<x-mail::panel>
**{{ $message->user->name }}** · {{ $message->user->email }}
Генераций на счету: {{ $message->user->credits }}, презентаций: {{ $message->user->presentations()->count() }}
</x-mail::panel>

<x-mail::button :url="route('admin.support.show', $message->user)">
Ответить в админке
</x-mail::button>

Следующие сообщения из этого диалога в ближайшие 10 минут придут без письма.
</x-mail::message>
