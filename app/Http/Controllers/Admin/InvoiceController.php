<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Список всех счетов
     */
    public function index(Request $request)
    {
        $query = Invoice::with('user')->orderBy('invoice_date', 'desc')->orderBy('id', 'desc');

        // Фильтр по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Поиск по номеру или email пользователя
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->paginate(20);

        // Статистика по счетам
        $stats = [
            'total' => Invoice::count(),
            'draft' => Invoice::where('status', 'draft')->count(),
            'sent' => Invoice::where('status', 'sent')->count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'closed' => Invoice::where('status', 'closed')->count(),
            'cancelled' => Invoice::where('status', 'cancelled')->count(),
            'total_amount' => Invoice::whereIn('status', ['paid', 'closed'])->sum('subtotal'),
            'spent_amount' => Invoice::whereIn('status', ['paid', 'closed'])->sum('spent_amount'),
        ];

        return view('admin.billing.invoices.index', compact('invoices', 'stats'));
    }

    /**
     * Просмотр счета
     */
    public function show($id)
    {
        $invoice = Invoice::with(['user', 'items'])->findOrFail($id);

        return view('admin.billing.invoices.show', compact('invoice'));
    }

    /**
     * Скачивание счета в PDF (для админа)
     */
    public function download($id)
    {
        $invoice = Invoice::with(['user', 'items'])->findOrFail($id);

        $seller = \App\Models\BillingSettings::current();
        $buyer = $invoice->user;

        // Генерируем PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', compact('invoice', 'seller', 'buyer'));

        return $pdf->download('Счет_' . $invoice->number . '.pdf');
    }

    /**
     * Скачивание акта по счету в PDF
     */
    public function downloadAct($id)
    {
        $invoice = Invoice::with(['user'])->findOrFail($id);

        // Проверяем, что счет оплачен
        if (!in_array($invoice->status, ['paid', 'closed'])) {
            return redirect()->back()->with('error', 'Акт можно сформировать только для оплаченных счетов');
        }

        // Собираем все списания по этому счету
        $user = $invoice->user;
        $transactions = [];

        // BalanceCharge
        $balanceCharges = \App\Models\BalanceCharge::where('user_id', $user->id)
            ->where('created_at', '>=', $invoice->paid_at)
            ->orderBy('created_at')
            ->get();

        foreach ($balanceCharges as $charge) {
            if ($charge->amount <= $invoice->remaining_amount) {
                $transactions[] = [
                    'date' => $charge->created_at,
                    'description' => $charge->description,
                    'amount' => $charge->amount,
                ];
            }
        }

        // SubscriptionCharge
        $subscriptionCharges = \App\Models\SubscriptionCharge::where('user_id', $user->id)
            ->where('charged_at', '>=', $invoice->paid_at)
            ->orderBy('charged_at')
            ->get();

        foreach ($subscriptionCharges as $charge) {
            $transactions[] = [
                'date' => $charge->charged_at,
                'description' => $charge->description,
                'amount' => $charge->amount,
            ];
        }

        // ReportAccess
        $reportAccesses = \App\Models\ReportAccess::where('user_id', $user->id)
            ->where('accessed_at', '>=', $invoice->paid_at)
            ->where('price', '>', 0)
            ->orderBy('accessed_at')
            ->get();

        foreach ($reportAccesses as $access) {
            $transactions[] = [
                'date' => $access->accessed_at,
                'description' => 'Открытие отчета #' . ($access->report_number ?? $access->report_id),
                'amount' => $access->price,
            ];
        }

        // ItemPurchase
        $itemPurchases = \App\Models\ItemPurchase::where('user_id', $user->id)
            ->where('created_at', '>=', $invoice->paid_at)
            ->orderBy('created_at')
            ->get();

        foreach ($itemPurchases as $purchase) {
            $transactions[] = [
                'date' => $purchase->created_at,
                'description' => 'Покупка доступа к позиции #' . $purchase->item_id,
                'amount' => $purchase->amount,
            ];
        }

        // Сортируем по дате
        usort($transactions, function($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        // Ограничиваем сумму расходов суммой счета
        $totalSpent = 0;
        $actTransactions = [];
        foreach ($transactions as $transaction) {
            if ($totalSpent + $transaction['amount'] <= $invoice->subtotal) {
                $actTransactions[] = $transaction;
                $totalSpent += $transaction['amount'];
            } else {
                break;
            }
        }

        $seller = \App\Models\BillingSettings::current();
        $buyer = $invoice->user;

        // Генерируем PDF акта
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('acts.invoice-act', compact('invoice', 'seller', 'buyer', 'actTransactions', 'totalSpent'));

        return $pdf->download('Акт_к_счету_' . $invoice->number . '.pdf');
    }

    /**
     * Отметить счет как оплаченный
     */
    public function markAsPaid($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status === 'paid') {
            return redirect()->back()->with('warning', 'Счет уже отмечен как оплаченный');
        }

        // Используем метод модели для отметки как оплаченный
        $invoice->markAsPaid();

        return redirect()->back()->with('success', "Счет #{$invoice->number} отмечен как оплаченный. На баланс пользователя начислено {$invoice->subtotal} ₽");
    }

    /**
     * Снять отметку об оплате
     */
    public function markAsUnpaid($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status !== 'paid') {
            return redirect()->back()->with('warning', 'Счет не отмечен как оплаченный');
        }

        // Используем метод модели для снятия отметки
        $invoice->markAsUnpaid();

        return redirect()->back()->with('success', "Отметка об оплате снята со счета #{$invoice->number}. С баланса пользователя списано {$invoice->subtotal} ₽");
    }

    /**
     * Отменить счет
     */
    public function cancel($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status === 'cancelled') {
            return redirect()->back()->with('warning', 'Счет уже отменён');
        }

        // Используем метод модели для отмены
        $invoice->cancel();

        $message = "Счет #{$invoice->number} отменён.";
        if ($invoice->status === 'paid') {
            $message .= " С баланса пользователя списано {$invoice->subtotal} ₽";
        }

        return redirect()->back()->with('success', $message);
    }
}
