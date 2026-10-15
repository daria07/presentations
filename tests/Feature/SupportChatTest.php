<?php

use App\Mail\SupportMessageReceived;
use App\Mail\SupportReplied;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Чат с администратором: человек пишет из окошка на сайте,
   администратор отвечает в админке, обоим приходят письма.
*/

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    config([
        'admin.emails' => ['boss@example.com'],
        'mail.feedback_to' => 'boss@example.com',
    ]);
    $this->admin = User::factory()->create(['email' => 'boss@example.com']);
    $this->user = User::factory()->create();
});

test('человек пишет — сообщение сохраняется, администратору письмо', function () {
    $this->actingAs($this->user)
        ->postJson('/support', ['body' => '  Не скачивается PDF  '])
        ->assertCreated()
        ->assertJsonPath('message.body', 'Не скачивается PDF')
        ->assertJsonPath('message.fromAdmin', false);

    expect(SupportMessage::where('user_id', $this->user->id)->count())->toBe(1);
    Mail::assertQueued(SupportMessageReceived::class, fn ($mail) => $mail->hasTo('boss@example.com'));
});

test('несколько сообщений подряд — одно письмо', function () {
    foreach (['Привет', 'Не скачивается', 'Помогите'] as $text) {
        $this->actingAs($this->user)->postJson('/support', ['body' => $text])->assertCreated();
    }

    Mail::assertQueuedCount(1);
});

test('пустое и слишком длинное сообщение не принимаются', function () {
    $this->actingAs($this->user)->postJson('/support', ['body' => ''])->assertUnprocessable();
    $this->actingAs($this->user)->postJson('/support', ['body' => str_repeat('а', 2001)])->assertUnprocessable();

    expect(SupportMessage::count())->toBe(0);
});

test('человек видит только свою переписку и только новые сообщения после after', function () {
    $other = User::factory()->create();
    $other->supportMessages()->create(['body' => 'чужое']);
    $first = $this->user->supportMessages()->create(['body' => 'первое']);
    $this->user->supportMessages()->create(['body' => 'ответ', 'from_admin' => true]);

    $this->actingAs($this->user)
        ->getJson('/support')
        ->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonMissing(['body' => 'чужое']);

    $this->actingAs($this->user)
        ->getJson('/support?after='.$first->id)
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', 'ответ');
});

test('открыл окошко — ответы прочитаны, точка на кнопке гаснет', function () {
    $this->user->supportMessages()->create(['body' => 'ответ', 'from_admin' => true]);

    $this->actingAs($this->user)
        ->get('/presentations')
        ->assertInertia(fn (Assert $page) => $page->where('support.unread', 1));

    $this->actingAs($this->user)->getJson('/support')->assertOk();

    $this->actingAs($this->user)
        ->get('/presentations')
        ->assertInertia(fn (Assert $page) => $page->where('support.unread', 0));
});

test('администратор видит диалоги с непрочитанными и читает диалог', function () {
    $this->user->supportMessages()->create(['body' => 'вопрос 1']);
    $this->user->supportMessages()->create(['body' => 'вопрос 2']);

    $this->actingAs($this->admin)
        ->get('/admin/support')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Support')
            ->where('support.adminUnread', 1)
            ->where('conversations.items.0.userId', $this->user->id)
            ->where('conversations.items.0.unread', 2)
            ->where('thread', null));

    $this->actingAs($this->admin)
        ->get('/admin/support/'.$this->user->id)
        ->assertInertia(fn (Assert $page) => $page
            ->has('thread.messages', 2)
            ->where('thread.user.email', $this->user->email));

    expect(SupportMessage::query()->unreadByAdmin()->count())->toBe(0);
});

test('ответ администратора виден человеку и уходит ему письмом', function () {
    $this->user->supportMessages()->create(['body' => 'вопрос']);

    $this->actingAs($this->admin)
        ->post('/admin/support/'.$this->user->id, ['body' => 'Уже починили'])
        ->assertRedirect('/admin/support/'.$this->user->id);

    Mail::assertQueued(SupportReplied::class, fn ($mail) => $mail->hasTo($this->user->email));

    $this->actingAs($this->user)
        ->getJson('/support')
        ->assertJsonPath('messages.1.body', 'Уже починили')
        ->assertJsonPath('messages.1.fromAdmin', true);
});

test('посторонний не видит админский чат и не может ответить', function () {
    $this->actingAs($this->user)->get('/admin/support')->assertNotFound();
    $this->actingAs($this->user)->post('/admin/support/'.$this->admin->id, ['body' => 'x'])->assertNotFound();
});

test('без входа чат закрыт', function () {
    $this->getJson('/support')->assertUnauthorized();
    $this->postJson('/support', ['body' => 'x'])->assertUnauthorized();
});
