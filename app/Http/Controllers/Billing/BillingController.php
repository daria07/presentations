<?php

namespace App\Http\Controllers\Billing;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\Billing;
use App\Services\Billing\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class BillingController extends Controller
{
    /** Страница пополнения */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('billing/Index', [
            'packages' => collect(Package::all($user->email))->map(fn (Package $p) => [
                'key' => $p->key,
                'title' => $p->title,
                'credits' => $p->credits,
                'amount' => $p->amountForHumans(),
                'perCredit' => number_format($p->pricePerCredit() / 100, 0, ',', ' '),
                'note' => $p->note,
                'popular' => $p->popular,
            ]),
            'credits' => $user->credits,
            'trialAvailable' => ! $user->trial_used,
            'history' => $user->payments()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'amount' => $p->amountForHumans(),
                    // Сумма числом нужна цели Метрики: в Директе по ней
                    // считается доход с рекламы
                    'amountValue' => round($p->amount / 100, 2),
                    'credits' => $p->credits_granted,
                    'status' => $p->status->value,
                    'statusLabel' => $p->status->label(),
                    'date' => $p->created_at?->toIso8601String(),
                    'receipt' => $p->hasReceipt()
                        ? route('billing.receipt', $p)
                        : null,
                ]),
        ]);
    }

    /**
     * Уводим человека на оплату.
     *
     * Inertia::location, а не redirect()->away: кнопка отправляет POST
     * через XHR, и обычное перенаправление браузер отработал бы внутри
     * этого же запроса — на чужой домен так нельзя, ответ не придёт, а
     * кнопка навсегда останется в состоянии «Переходим…». location
     * отвечает кодом 409 с заголовком, по которому клиент Inertia
     * уходит на адрес целой страницей.
     */
    public function checkout(Request $request): RedirectResponse|SymfonyResponse
    {
        // Список для проверки — тот же, что и на витрине: чужой ключ
        // пакета не пройдёт, даже если его подставить руками в запрос
        $available = collect(Package::all($request->user()->email))
            ->pluck('key')
            ->all();

        $request->validate([
            'package' => ['required', 'string', 'in:'.implode(',', $available)],
        ]);

        try {
            $url = Billing::make()->start(
                $request->user(),
                Package::find($request->string('package')),
                route('billing.index'),
            );
        } catch (Throwable $e) {
            report($e);

            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Не получилось создать платёж. Попробуйте ещё раз.',
            ]);
        }

        return Inertia::location($url);
    }

    /**
     * Чек по платежу.
     *
     * Ссылка на «Мой налог» — перенаправлением, файл — отдаём сами:
     * он лежит вне публичной папки, иначе чужой чек открывался бы по
     * прямому адресу без всякой проверки.
     */
    public function receipt(Request $request, Payment $payment): SymfonyResponse
    {
        abort_unless(
            $payment->user_id === $request->user()?->id || $request->user()?->isAdmin(),
            404,
        );

        if (filled($payment->receipt_url)) {
            return redirect()->away($payment->receipt_url);
        }

        abort_unless(filled($payment->receipt_path), 404);
        abort_unless(Storage::disk('local')->exists($payment->receipt_path), 404);

        return Storage::disk('local')->download(
            $payment->receipt_path,
            'Чек '.$payment->amountForHumans().' ₽.'.pathinfo($payment->receipt_path, PATHINFO_EXTENSION),
        );
    }

    /**
     * Вебхук провайдера. Без авторизации и без CSRF —
     * подпись проверяет сам провайдер внутри parseWebhook.
     */
    public function webhook(Request $request)
    {
        Billing::make()->handleWebhook($request->all(), $request->headers->all());

        // Провайдеру важен только код ответа: 200 значит «принято»,
        // иначе он будет слать это уведомление снова и снова.
        return response()->noContent();
    }

    // -----------------------------------------------------------------
    // Песочница: живёт только при провайдере fake
    // -----------------------------------------------------------------

    public function sandbox(Payment $payment): Response
    {
        abort_unless(config('billing.provider') === 'fake', 404);
        $this->authorizeOwnership($payment);

        return Inertia::render('billing/Sandbox', [
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amountForHumans(),
                'credits' => $payment->credits_granted,
            ],
        ]);
    }

    public function sandboxSettle(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless(config('billing.provider') === 'fake', 404);
        $this->authorizeOwnership($payment);

        $paid = $request->boolean('paid');

        Billing::make()->settle($payment->provider_payment_id, $paid);

        return to_route('billing.index')->with('toast', [
            'type' => $paid ? 'success' : 'info',
            'message' => $paid
                ? "Начислено генераций: {$payment->credits_granted}"
                : 'Оплата отменена.',
        ]);
    }

    private function authorizeOwnership(Payment $payment): void
    {
        abort_unless($payment->user_id === request()->user()?->id, 403);
        abort_unless($payment->status === PaymentStatus::Pending, 404);
    }
}
