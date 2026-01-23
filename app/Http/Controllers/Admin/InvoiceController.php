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

        return view('admin.billing.invoices.index', compact('invoices'));
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
