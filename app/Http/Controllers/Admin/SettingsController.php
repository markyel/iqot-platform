<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $unlockPrice = Setting::get('item_unlock_price', 99);
        $pricePerItem = SystemSetting::get('price_per_item', 50);

        // Настройки тарифов для лендинга
        $pricingMonitoring = SystemSetting::get('pricing_monitoring', 396);
        $pricingReportUnlock = SystemSetting::get('pricing_report_unlock', 99);

        // Тарифы подписки
        $subscriptionBasicPrice = SystemSetting::get('subscription_basic_price', 5000);
        $subscriptionBasicPositions = SystemSetting::get('subscription_basic_positions', 15);
        $subscriptionBasicReports = SystemSetting::get('subscription_basic_reports', 5);
        $subscriptionBasicOverlimitPosition = SystemSetting::get('subscription_basic_overlimit_position', 300);
        $subscriptionBasicOverlimitReport = SystemSetting::get('subscription_basic_overlimit_report', 89);

        $subscriptionAdvancedPrice = SystemSetting::get('subscription_advanced_price', 15000);
        $subscriptionAdvancedPositions = SystemSetting::get('subscription_advanced_positions', 50);
        $subscriptionAdvancedReports = SystemSetting::get('subscription_advanced_reports', 15);
        $subscriptionAdvancedOverlimitPosition = SystemSetting::get('subscription_advanced_overlimit_position', 270);
        $subscriptionAdvancedOverlimitReport = SystemSetting::get('subscription_advanced_overlimit_report', 79);

        $subscriptionProPrice = SystemSetting::get('subscription_pro_price', 50000);
        $subscriptionProPositions = SystemSetting::get('subscription_pro_positions', 200);
        $subscriptionProReports = SystemSetting::get('subscription_pro_reports', 50);
        $subscriptionProOverlimitPosition = SystemSetting::get('subscription_pro_overlimit_position', 240);
        $subscriptionProOverlimitReport = SystemSetting::get('subscription_pro_overlimit_report', 69);

        return view('admin.settings.index', compact(
            'unlockPrice',
            'pricePerItem',
            'pricingMonitoring',
            'pricingReportUnlock',
            'subscriptionBasicPrice',
            'subscriptionBasicPositions',
            'subscriptionBasicReports',
            'subscriptionBasicOverlimitPosition',
            'subscriptionBasicOverlimitReport',
            'subscriptionAdvancedPrice',
            'subscriptionAdvancedPositions',
            'subscriptionAdvancedReports',
            'subscriptionAdvancedOverlimitPosition',
            'subscriptionAdvancedOverlimitReport',
            'subscriptionProPrice',
            'subscriptionProPositions',
            'subscriptionProReports',
            'subscriptionProOverlimitPosition',
            'subscriptionProOverlimitReport'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'item_unlock_price' => 'required|numeric|min:0',
            'price_per_item' => 'required|numeric|min:0',
            'pricing_monitoring' => 'required|numeric|min:0',
            'pricing_report_unlock' => 'required|numeric|min:0',
            'subscription_basic_price' => 'required|numeric|min:0',
            'subscription_basic_positions' => 'required|integer|min:0',
            'subscription_basic_reports' => 'required|integer|min:0',
            'subscription_basic_overlimit_position' => 'required|numeric|min:0',
            'subscription_basic_overlimit_report' => 'required|numeric|min:0',
            'subscription_advanced_price' => 'required|numeric|min:0',
            'subscription_advanced_positions' => 'required|integer|min:0',
            'subscription_advanced_reports' => 'required|integer|min:0',
            'subscription_advanced_overlimit_position' => 'required|numeric|min:0',
            'subscription_advanced_overlimit_report' => 'required|numeric|min:0',
            'subscription_pro_price' => 'required|numeric|min:0',
            'subscription_pro_positions' => 'required|integer|min:0',
            'subscription_pro_reports' => 'required|integer|min:0',
            'subscription_pro_overlimit_position' => 'required|numeric|min:0',
            'subscription_pro_overlimit_report' => 'required|numeric|min:0',
        ]);

        Setting::set('item_unlock_price', $request->item_unlock_price);
        SystemSetting::set('price_per_item', $request->price_per_item);

        // Тарифы для лендинга
        SystemSetting::set('pricing_monitoring', $request->pricing_monitoring);
        SystemSetting::set('pricing_report_unlock', $request->pricing_report_unlock);

        // Базовый тариф
        SystemSetting::set('subscription_basic_price', $request->subscription_basic_price);
        SystemSetting::set('subscription_basic_positions', $request->subscription_basic_positions);
        SystemSetting::set('subscription_basic_reports', $request->subscription_basic_reports);
        SystemSetting::set('subscription_basic_overlimit_position', $request->subscription_basic_overlimit_position);
        SystemSetting::set('subscription_basic_overlimit_report', $request->subscription_basic_overlimit_report);

        // Расширенный тариф
        SystemSetting::set('subscription_advanced_price', $request->subscription_advanced_price);
        SystemSetting::set('subscription_advanced_positions', $request->subscription_advanced_positions);
        SystemSetting::set('subscription_advanced_reports', $request->subscription_advanced_reports);
        SystemSetting::set('subscription_advanced_overlimit_position', $request->subscription_advanced_overlimit_position);
        SystemSetting::set('subscription_advanced_overlimit_report', $request->subscription_advanced_overlimit_report);

        // Профессиональный тариф
        SystemSetting::set('subscription_pro_price', $request->subscription_pro_price);
        SystemSetting::set('subscription_pro_positions', $request->subscription_pro_positions);
        SystemSetting::set('subscription_pro_reports', $request->subscription_pro_reports);
        SystemSetting::set('subscription_pro_overlimit_position', $request->subscription_pro_overlimit_position);
        SystemSetting::set('subscription_pro_overlimit_report', $request->subscription_pro_overlimit_report);

        return redirect()->back()->with('success', 'Настройки обновлены');
    }
}
