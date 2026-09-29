<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Чеки НПД: список оплаченных платежей и загрузка чека к каждому.
 *
 * Единственное место в админке, где что-то пишется, — остальное только
 * читает. Здесь без этого никак: ЮKassa закрыла автоматическую выдачу
 * чеков самозанятым, и чек приходится выбивать вручную в «Мой налог»,
 * а сюда приносить ссылку или файл.
 */
class ReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        // По умолчанию показываем только то, с чем надо работать:
        // оплаченные платежи без чека. Со всеми — по флажку
        $onlyPending = ! $request->boolean('all');

        $payments = Payment::query()
            ->with('user:id,name,email')
            ->where('status', PaymentStatus::Paid)
            ->when($onlyPending, fn ($q) => $q->whereNull('receipt_url')->whereNull('receipt_path'))
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'amount' => $p->amountForHumans(),
                'credits' => $p->credits_granted,
                'date' => $p->created_at?->toIso8601String(),
                'email' => $p->user?->email,
                'name' => $p->user?->name,
                'receiptUrl' => $p->receipt_url,
                'hasFile' => filled($p->receipt_path),
                'addedAt' => $p->receipt_added_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/Receipts', [
            'payments' => $payments,
            'onlyPending' => $onlyPending,
            'waiting' => Payment::query()
                ->where('status', PaymentStatus::Paid)
                ->whereNull('receipt_url')
                ->whereNull('receipt_path')
                ->count(),
        ]);
    }

    public function store(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['nullable', 'url', 'max:500'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if (blank($data['url'] ?? null) && ! $request->hasFile('file')) {
            return back()->withErrors([
                'url' => 'Нужна ссылка на чек или файл.',
            ]);
        }

        $path = $payment->receipt_path;

        if ($request->hasFile('file')) {
            // Старый файл удаляем сразу: чек заменяют, когда в прошлом
            // была ошибка, и хранить неверный документ незачем
            if (filled($path)) {
                Storage::disk('local')->delete($path);
            }

            $path = $request->file('file')->store('receipts');
        }

        $payment->update([
            'receipt_url' => $data['url'] ?? null,
            'receipt_path' => $path,
            'receipt_added_at' => now(),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Чек добавлен.',
        ]);
    }
}
