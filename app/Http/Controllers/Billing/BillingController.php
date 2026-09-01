<?php

namespace App\Http\Controllers\Billing;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\Billing;
use App\Services\Billing\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BillingController extends Controller
{
    /** Страница пополнения */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('billing/Index', [
            'packages' => collect(Package::all())->map(fn (Package $p) => [
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
                    'credits' => $p->credits_granted,
                    'status' => $p->status->value,
                    'statusLabel' => $p->status->label(),
                    'date' => $p->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /** Уводим человека на оплату */
    public function checkout(Request $request): RedirectResponse
    {
        $request->validate([
            'package' => ['required', 'string', 'in:'.implode(',', array_keys(config('billing.packages')))],
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

        return redirect()->away($url);
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
