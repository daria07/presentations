<x-mail::message>
# Вам ответили

{{ $message->body }}

<x-mail::button :url="url('/presentations?support=open')">
Открыть чат
</x-mail::button>

Ответить можно в чате на сайте — кнопка «Написать нам» в правом нижнем углу.
</x-mail::message>
