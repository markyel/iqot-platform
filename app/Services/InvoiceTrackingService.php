<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Models\BalanceCharge;
use App\Models\SubscriptionCharge;
use App\Models\ReportAccess;
use App\Models\ItemPurchase;

/**
 * Сервис для отслеживания расходов по счетам
 *
 * Логика: Каждое списание средств привязывается к оплаченному счету (FIFO - first in, first out)
 */
class InvoiceTrackingService
{
    /**
     * Привязать списание к счету и обновить spent_amount
     *
     * ВАЖНО: Этот метод НЕ блокирует списания! Он только учитывает расходы для отчетности.
     * Списание всегда идет с общего баланса пользователя, независимо от наличия счетов.
     *
     * @param User $user
     * @param float $amount Сумма списания
     * @param string $type Тип списания (subscription, charge, report_access, item_purchase)
     * @param int|null $relatedId ID связанной записи
     * @return Invoice|null Счет, к которому привязано списание (или null если нет подходящего счета)
     */
    public function trackSpending(User $user, float $amount, string $type, ?int $relatedId = null): ?Invoice
    {
        if ($amount <= 0) {
            return null;
        }

        try {
            // Находим первый оплаченный счет с остатком средств (FIFO)
            $invoice = Invoice::where('user_id', $user->id)
                ->where('status', 'paid')
                ->whereColumn('spent_amount', '<', 'subtotal')
                ->orderBy('paid_at', 'asc')
                ->first();

            if (!$invoice) {
                // Это нормальная ситуация - пользователь может тратить средства без привязки к счетам
                \Log::info("No invoice with remaining balance for spending tracking. User: {$user->id}, Amount: {$amount}, Type: {$type}. Spending allowed, but not tracked to invoice.");
                return null;
            }

            // Добавляем расход к счету (может быть частичным, если остаток меньше суммы)
            $remainingInInvoice = $invoice->remaining_amount;
            $amountToTrack = min($amount, $remainingInInvoice);

            $invoice->addSpending($amountToTrack);

            \Log::info("Spending tracked. Invoice: #{$invoice->number}, User: {$user->id}, Amount: {$amountToTrack}/{$amount}, Type: {$type}, Related ID: {$relatedId}");

            // Если сумма больше остатка в счете, продолжаем с следующим счетом
            if ($amount > $amountToTrack) {
                $remaining = $amount - $amountToTrack;
                $this->trackSpending($user, $remaining, $type, $relatedId);
            }

            return $invoice;
        } catch (\Exception $e) {
            // Ошибка в трекинге НЕ должна блокировать списание
            \Log::error("Error tracking spending for user {$user->id}: " . $e->getMessage(), [
                'amount' => $amount,
                'type' => $type,
                'related_id' => $relatedId,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Обработать списание по абонентской плате
     */
    public function trackSubscriptionCharge(SubscriptionCharge $charge): ?Invoice
    {
        return $this->trackSpending(
            $charge->user,
            $charge->amount,
            'subscription',
            $charge->id
        );
    }

    /**
     * Обработать списание по позиции заявки
     */
    public function trackBalanceCharge(BalanceCharge $charge): ?Invoice
    {
        return $this->trackSpending(
            $charge->user,
            $charge->amount,
            'balance_charge',
            $charge->id
        );
    }

    /**
     * Обработать покупку доступа к отчету
     */
    public function trackReportAccess(ReportAccess $access): ?Invoice
    {
        if ($access->price <= 0) {
            return null;
        }

        return $this->trackSpending(
            $access->user,
            $access->price,
            'report_access',
            $access->id
        );
    }

    /**
     * Обработать покупку доступа к позиции
     */
    public function trackItemPurchase(ItemPurchase $purchase): ?Invoice
    {
        return $this->trackSpending(
            $purchase->user,
            $purchase->amount,
            'item_purchase',
            $purchase->id
        );
    }

    /**
     * Получить статистику по счетам пользователя
     */
    public function getUserInvoiceStats(User $user): array
    {
        $invoices = Invoice::where('user_id', $user->id)
            ->where('status', 'paid')
            ->orWhere('status', 'closed')
            ->get();

        $totalReceived = $invoices->sum('subtotal');
        $totalSpent = $invoices->sum('spent_amount');
        $remaining = $totalReceived - $totalSpent;

        $openInvoices = $invoices->where('status', 'paid')->count();
        $closedInvoices = $invoices->where('status', 'closed')->count();

        return [
            'total_received' => $totalReceived,
            'total_spent' => $totalSpent,
            'remaining' => $remaining,
            'open_invoices' => $openInvoices,
            'closed_invoices' => $closedInvoices,
        ];
    }
}
