<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\BillingSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    /**
     * Запрос на выставление счета
     */
    public function request(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $settings = \App\Models\BillingSettings::current();

        // Получаем ставку НДС из настроек
        $vatRate = ($settings && $settings->vat_enabled) ? $settings->vat_rate : 0;

        // Создаем счет с нулевыми суммами (будут пересчитаны после добавления позиций)
        $invoice = Invoice::create([
            'user_id' => $user->id,
            'number' => Invoice::generateNumber(),
            'invoice_date' => now(),
            'subtotal' => 0,
            'vat_rate' => $vatRate,
            'vat_amount' => 0,
            'total' => 0,
            'status' => 'draft',
            'description' => 'Авансовый платёж за услуги информационного сервиса IQOT согласно Договору-оферте, размещённому на сайте iqot.ru',
            'notes' => $validated['notes'],
        ]);

        // Создаем позицию счета
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'sort_order' => 1,
            'name' => 'Авансовый платёж за услуги информационного сервиса IQOT',
            'unit' => 'шт',
            'quantity' => 1,
            'price' => $validated['amount'],
        ]);

        // Пересчитываем суммы
        $invoice->recalculate();

        // Обновляем статус на "отправлен"
        $invoice->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return redirect()->route('cabinet.invoices.show', $invoice->id)
            ->with('success', 'Счет успешно выставлен. Вы можете скачать его в PDF формате.');
    }

    /**
     * Просмотр счета
     */
    public function show($id)
    {
        $invoice = Invoice::with('items')->findOrFail($id);

        // Проверяем, что счет принадлежит текущему пользователю
        if ($invoice->user_id !== Auth::id()) {
            abort(403, 'У вас нет доступа к этому счету');
        }

        $seller = BillingSettings::current();
        $buyer = $invoice->user;

        return view('cabinet.invoices.show', compact('invoice', 'seller', 'buyer'));
    }

    /**
     * Скачивание счета в PDF
     */
    public function download($id)
    {
        $invoice = Invoice::with('items')->findOrFail($id);

        // Проверяем, что счет принадлежит текущему пользователю
        if ($invoice->user_id !== Auth::id()) {
            abort(403, 'У вас нет доступа к этому счету');
        }

        $seller = BillingSettings::current();
        $buyer = $invoice->user;

        // Генерируем PDF
        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'seller', 'buyer'));

        return $pdf->download('Счет_' . $invoice->number . '.pdf');
    }

    /**
     * Список счетов пользователя
     */
    public function index()
    {
        $invoices = Invoice::where('user_id', Auth::id())
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('cabinet.invoices.index', compact('invoices'));
    }
}
