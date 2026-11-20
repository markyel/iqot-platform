<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    /**
     * Список всех рассылок
     */
    public function index()
    {
        $campaigns = Campaign::orderBy('created_at', 'desc')->paginate(20);
        return view('admin.campaigns.index', compact('campaigns'));
    }

    /**
     * Форма создания новой рассылки - Шаг 1: Основные данные
     */
    public function create()
    {
        return view('admin.campaigns.create');
    }

    /**
     * Сохранение рассылки и переход к шагу 2
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'html_template' => 'required|file|mimes:html,htm,txt|max:10240',
            'delay_seconds' => 'required|integer|min:1|max:60',
        ]);

        // Сохраняем HTML-шаблон
        $htmlContent = file_get_contents($request->file('html_template')->getRealPath());

        $campaign = Campaign::create([
            'name' => $request->name,
            'subject' => $request->subject,
            'html_template' => $htmlContent,
            'delay_seconds' => $request->delay_seconds,
            'status' => 'draft',
        ]);

        return redirect()->route('admin.campaigns.upload', $campaign)->with('success', 'Рассылка создана. Теперь загрузите данные получателей.');
    }

    /**
     * Редактирование рассылки
     */
    public function edit(Campaign $campaign)
    {
        if (!$campaign->isEditable()) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', 'Рассылку нельзя редактировать');
        }

        return view('admin.campaigns.edit', compact('campaign'));
    }

    /**
     * Обновление базовых данных рассылки
     */
    public function update(Request $request, Campaign $campaign)
    {
        if (!$campaign->isEditable()) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', 'Рассылку нельзя редактировать');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'delay_seconds' => 'required|integer|min:1|max:60',
        ]);

        $campaign->update([
            'name' => $request->name,
            'subject' => $request->subject,
            'delay_seconds' => $request->delay_seconds,
        ]);

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Рассылка обновлена');
    }

    /**
     * Шаг 2: Загрузка CSV файлов
     */
    public function upload(Campaign $campaign)
    {
        if (!$campaign->isEditable()) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', 'Рассылку нельзя редактировать в текущем статусе');
        }

        return view('admin.campaigns.upload', compact('campaign'));
    }

    /**
     * Обработка загрузки файлов и переход к шагу 3 (маппинг)
     */
    public function processUpload(Request $request, Campaign $campaign)
    {
        $request->validate([
            'template_file' => 'nullable|file|mimes:html,htm|max:10240',
            'recipients_file' => 'nullable|file|mimes:csv,txt|max:10240',
            'data_file' => 'nullable|file|mimes:csv,txt|max:10240',
        ]);

        // Проверяем, что хотя бы один файл загружен
        if (!$request->hasFile('template_file') && !$request->hasFile('recipients_file') && !$request->hasFile('data_file')) {
            return back()->with('error', 'Загрузите хотя бы один файл для обновления');
        }

        // Обновляем шаблон, если загружен новый
        if ($request->hasFile('template_file')) {
            $htmlTemplate = $request->file('template_file')->get();
            $campaign->update(['html_template' => $htmlTemplate]);
        }

        // Парсим файл с получателями (если загружен) или используем существующие данные
        $recipientsData = null;
        if ($request->hasFile('recipients_file')) {
            $recipientsData = $this->parseCSV($request->file('recipients_file'));
        } else {
            // Восстанавливаем данные из существующих получателей
            $existingRecipients = $campaign->recipients()->get();
            if ($existingRecipients->isNotEmpty()) {
                $recipientsData = $existingRecipients->map(function($recipient) {
                    return array_merge(['email' => $recipient->email], $recipient->data);
                })->toArray();
            }
        }

        // Парсим дополнительный файл с данными (если есть)
        $additionalData = null;
        if ($request->hasFile('data_file')) {
            $additionalData = $this->parseTXT($request->file('data_file'));
        }

        // Если нет данных получателей - нельзя продолжить
        if (!$recipientsData) {
            return back()->with('error', 'Загрузите файл с получателями или сначала создайте рассылку с данными');
        }

        // Сохраняем в сессию для следующего шага
        session([
            'campaign_recipients_data' => $recipientsData,
            'campaign_additional_data' => $additionalData,
        ]);

        return redirect()->route('admin.campaigns.mapping', $campaign);
    }

    /**
     * Шаг 3: Маппинг полей
     */
    public function mapping(Campaign $campaign)
    {
        if (!$campaign->isEditable()) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', 'Рассылку нельзя редактировать');
        }

        $recipientsData = session('campaign_recipients_data');
        $additionalData = session('campaign_additional_data');

        if (!$recipientsData) {
            return redirect()->route('admin.campaigns.upload', $campaign)->with('error', 'Сначала загрузите файлы с данными');
        }

        // Извлекаем переменные из шаблона
        $templateVariables = $campaign->extractTemplateVariables();

        // Извлекаем изображения из шаблона
        $templateImages = $campaign->extractTemplateImages();

        // Получаем колонки из загруженных файлов
        $recipientsColumns = array_keys($recipientsData[0]);

        // Создаем список доступных источников данных
        $dataSources = [];

        // Служебные значения
        $dataSources['_unsubscribe_url'] = '🔧 Служебное: URL отписки';

        // Колонки из файла получателей
        foreach ($recipientsColumns as $column) {
            $dataSources['recipients_' . $column] = 'Получатели: ' . $column;
        }

        // Дополнительный файл (построчно)
        if ($additionalData) {
            $dataSources['additional_line'] = 'Доп. файл (по порядку)';
        }

        return view('admin.campaigns.mapping', compact(
            'campaign',
            'templateVariables',
            'templateImages',
            'dataSources',
            'recipientsData',
            'additionalData'
        ));
    }

    /**
     * Сохранение маппинга и создание получателей
     */
    public function saveMapping(Request $request, Campaign $campaign)
    {
        $recipientsData = session('campaign_recipients_data');
        $additionalData = session('campaign_additional_data');

        if (!$recipientsData) {
            return redirect()->route('admin.campaigns.upload', $campaign)->with('error', 'Данные получателей не найдены');
        }

        // Проверяем, что email был выбран
        if (!$request->has('email_source')) {
            return back()->with('error', 'Необходимо указать источник для email');
        }

        // Обрабатываем загруженные изображения
        $templateImages = $campaign->extractTemplateImages();
        if (!empty($templateImages)) {
            // Удаляем старые изображения
            foreach ($campaign->images as $oldImage) {
                if (\Storage::disk('public')->exists($oldImage->file_path)) {
                    \Storage::disk('public')->delete($oldImage->file_path);
                }
            }
            $campaign->images()->delete();

            // Сохраняем новые изображения
            foreach ($templateImages as $imageSrc) {
                $fieldName = 'image_' . md5($imageSrc);

                if ($request->hasFile($fieldName)) {
                    $file = $request->file($fieldName);
                    $path = $file->store('campaign-images', 'public');
                    $cid = 'img_' . uniqid();

                    \App\Models\CampaignImage::create([
                        'campaign_id' => $campaign->id,
                        'original_src' => $imageSrc,
                        'file_path' => $path,
                        'cid' => $cid,
                    ]);
                }
            }
        }

        // Сохраняем маппинг
        $fieldMapping = $request->except(['_token', 'email_source']);
        // Удаляем поля изображений из маппинга
        foreach ($templateImages as $imageSrc) {
            $fieldName = 'image_' . md5($imageSrc);
            unset($fieldMapping[$fieldName]);
        }

        $emailSource = $request->email_source;

        $campaign->update(['field_mapping' => $fieldMapping]);

        // Удаляем старых получателей (если есть)
        $campaign->recipients()->delete();

        // Создаем получателей
        $additionalIndex = 0;
        foreach ($recipientsData as $index => $recipientRow) {
            // Получаем email
            $email = $this->extractValue($emailSource, $recipientRow, $additionalData, $additionalIndex);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue; // Пропускаем невалидные email
            }

            // Собираем все данные для подстановки
            $data = [];
            foreach ($fieldMapping as $variable => $source) {
                // Для URL отписки сохраняем маркер, заменим при отправке
                if ($source === '_unsubscribe_url') {
                    $data[$variable] = '__UNSUBSCRIBE_URL__';
                } else {
                    $data[$variable] = $this->extractValue($source, $recipientRow, $additionalData, $additionalIndex);
                }
            }

            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'email' => $email,
                'data' => $data,
                'status' => 'pending',
                'email_validated' => false,
            ]);

            $additionalIndex++;
        }

        // Обновляем счетчик
        $campaign->update(['total_recipients' => $campaign->recipients()->count()]);

        // Очищаем сессию
        session()->forget(['campaign_recipients_data', 'campaign_additional_data']);

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Рассылка готова к отправке!');
    }

    /**
     * Просмотр рассылки
     */
    public function show(Campaign $campaign)
    {
        $recipients = $campaign->recipients()->paginate(50);

        // Статистика валидации
        $validationStats = [
            'total_validated' => $campaign->recipients()->where('email_validated', true)->count(),
            'valid' => $campaign->recipients()->where('validation_status', 'valid')->count(),
            'invalid' => $campaign->recipients()->where('validation_status', 'invalid')->count(),
            'not_validated' => $campaign->recipients()->where('email_validated', false)->count(),
        ];

        return view('admin.campaigns.show', compact('campaign', 'recipients', 'validationStats'));
    }

    /**
     * Отправка тестового письма
     */
    public function sendTest(Request $request, Campaign $campaign)
    {
        $request->validate(['test_email' => 'required|email']);

        // Берем данные первого получателя для теста
        $firstRecipient = $campaign->recipients()->first();
        if (!$firstRecipient) {
            return back()->with('error', 'Нет получателей в рассылке');
        }

        try {
            // Ищем или создаем тестового получателя с указанным email
            $testRecipient = $campaign->recipients()
                ->where('email', $request->test_email)
                ->first();

            if (!$testRecipient) {
                // Создаем временного тестового получателя с данными первого
                $testRecipient = CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'email' => $request->test_email,
                    'data' => $firstRecipient->data,
                    'status' => 'sent', // Сразу помечаем как отправленное, чтобы не попало в массовую рассылку
                ]);
            }

            $html = $campaign->renderTemplate($testRecipient->data);

            // Заменяем маркер URL отписки на реальный URL для теста
            $unsubscribeUrl = route('campaign.unsubscribe', ['recipient' => $testRecipient->id]);
            $html = str_replace('__UNSUBSCRIBE_URL__', $unsubscribeUrl, $html);

            // Заменяем src изображений на CID
            foreach ($campaign->images as $image) {
                $html = str_replace($image->original_src, 'cid:' . $image->cid, $html);
            }

            $this->sendEmail($request->test_email, $campaign->subject, $html, $campaign);
            return back()->with('success', 'Тестовое письмо отправлено на ' . $request->test_email);
        } catch (\Exception $e) {
            return back()->with('error', 'Ошибка отправки: ' . $e->getMessage());
        }
    }

    /**
     * Запуск рассылки (асинхронно через Queue)
     */
    public function start(Request $request, Campaign $campaign)
    {
        if (!$campaign->canStart()) {
            return back()->with('error', 'Рассылку нельзя запустить в текущем статусе');
        }

        // Проверяем настройки SMTP
        $smtpHost = SystemSetting::get('smtp_host');
        $fromAddress = SystemSetting::get('smtp_from_address');

        if (!$smtpHost || !$fromAddress) {
            return back()->with('error', 'SMTP не настроен в системе');
        }

        // Обновляем настройку использования валидации email
        $useEmailValidation = $request->input('use_email_validation', true);

        $campaign->update([
            'status' => 'sending',
            'started_at' => now(),
            'use_email_validation' => $useEmailValidation,
        ]);

        // Получаем всех получателей со статусом pending
        $recipients = $campaign->recipients()->where('status', 'pending')->get();

        // Добавляем задачи в очередь с задержкой между письмами
        $delaySeconds = $campaign->delay_seconds;
        $currentDelay = 0;

        foreach ($recipients as $recipient) {
            \App\Jobs\SendCampaignEmail::dispatch($recipient->id, $campaign->id)
                ->delay(now()->addSeconds($currentDelay));

            $currentDelay += $delaySeconds;
        }

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', "Рассылка запущена! {$recipients->count()} писем добавлено в очередь. Отслеживайте прогресс на этой странице.");
    }

    /**
     * Дорассылка для получателей с ошибками
     */
    public function retry(Request $request, Campaign $campaign)
    {
        // Проверяем настройки SMTP
        $smtpHost = SystemSetting::get('smtp_host');
        $fromAddress = SystemSetting::get('smtp_from_address');

        if (!$smtpHost || !$fromAddress) {
            return back()->with('error', 'SMTP не настроен в системе');
        }

        // Обновляем настройку использования валидации email
        $useEmailValidation = $request->input('use_email_validation', false);

        $campaign->update([
            'use_email_validation' => $useEmailValidation,
        ]);

        // Получаем получателей с ошибками валидации (error_credit и др.)
        $recipients = $campaign->recipients()
            ->where('status', 'failed')
            ->where(function($query) {
                $query->where('error_message', 'like', '%error_credit%')
                      ->orWhere('validation_status', 'error_credit')
                      ->orWhereNull('email_validated'); // Непроверенные тоже включаем
            })
            ->get();

        if ($recipients->isEmpty()) {
            return back()->with('error', 'Нет получателей для дорассылки');
        }

        // Сбрасываем статус на pending для повторной отправки
        foreach ($recipients as $recipient) {
            $recipient->update([
                'status' => 'pending',
                'error_message' => null,
                'sent_at' => null,
            ]);

            // Если отключена валидация, сбрасываем флаг валидации
            if (!$useEmailValidation) {
                $recipient->update([
                    'email_validated' => true,
                    'validation_status' => 'skipped',
                    'validation_result' => json_encode(['valid' => true, 'reason' => 'Validation skipped']),
                ]);
            }
        }

        // Уменьшаем счетчик failed
        $campaign->decrement('failed_count', $recipients->count());

        // Добавляем задачи в очередь
        $delaySeconds = $campaign->delay_seconds;
        $currentDelay = 0;

        foreach ($recipients as $recipient) {
            \App\Jobs\SendCampaignEmail::dispatch($recipient->id, $campaign->id)
                ->delay(now()->addSeconds($currentDelay));

            $currentDelay += $delaySeconds;
        }

        // Обновляем статус кампании если нужно
        if ($campaign->status === 'completed' || $campaign->status === 'failed') {
            $campaign->update(['status' => 'sending']);
        }

        return redirect()->route('admin.campaigns.show', $campaign)
            ->with('success', "Дорассылка запущена! {$recipients->count()} писем добавлено в очередь.");
    }

    /**
     * API: Получение прогресса отправки
     */
    public function progress(Campaign $campaign)
    {
        $total = $campaign->total_recipients;
        $sent = $campaign->sent_count;
        $failed = $campaign->failed_count;
        $pending = $campaign->recipients()->where('status', 'pending')->count();

        $percentComplete = $total > 0 ? round(($sent + $failed) / $total * 100, 1) : 0;

        // Проверяем, завершена ли рассылка
        if ($campaign->status === 'sending' && $pending === 0) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'status' => $campaign->status,
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $pending,
            'percent_complete' => $percentComplete,
            'is_completed' => $campaign->status === 'completed',
        ]);
    }

    /**
     * Удаление рассылки
     */
    public function destroy(Campaign $campaign)
    {
        if (!$campaign->isEditable()) {
            return back()->with('error', 'Нельзя удалить рассылку в текущем статусе');
        }

        $campaign->delete();
        return redirect()->route('admin.campaigns.index')->with('success', 'Рассылка удалена');
    }

    /**
     * Отписка от рассылки
     */
    public function unsubscribe(CampaignRecipient $recipient)
    {
        if ($recipient->status === 'unsubscribed') {
            return view('campaigns.unsubscribe', [
                'message' => 'Вы уже отписаны от рассылки',
                'email' => $recipient->email
            ]);
        }

        $recipient->update(['status' => 'unsubscribed']);

        return view('campaigns.unsubscribe', [
            'message' => 'Вы успешно отписались от рассылки',
            'email' => $recipient->email
        ]);
    }

    /**
     * Парсинг CSV файла
     */
    private function parseCSV($file)
    {
        $data = [];
        $handle = fopen($file->getRealPath(), 'r');

        // Читаем заголовки
        $headers = fgetcsv($handle, 0, ',');

        // Удаляем BOM если есть
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
        }

        // Читаем строки
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        fclose($handle);
        return $data;
    }

    /**
     * Парсинг TXT файла (построчно)
     */
    private function parseTXT($file)
    {
        return file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    /**
     * Извлечение значения из источника данных
     */
    private function extractValue($source, $recipientRow, $additionalData, $additionalIndex, $recipientId = null)
    {
        // Служебное значение - URL отписки
        if ($source === '_unsubscribe_url') {
            if ($recipientId) {
                return route('campaign.unsubscribe', ['recipient' => $recipientId]);
            }
            return '#unsubscribe';
        }

        if (str_starts_with($source, 'recipients_')) {
            $column = str_replace('recipients_', '', $source);
            return $recipientRow[$column] ?? '';
        }

        if ($source === 'additional_line' && $additionalData) {
            return $additionalData[$additionalIndex] ?? '';
        }

        return '';
    }

    /**
     * Отправка email через SMTP из настроек
     */
    private function sendEmail($to, $subject, $html, $campaign = null)
    {
        $smtpHost = SystemSetting::get('smtp_host');
        $smtpPort = SystemSetting::get('smtp_port', 587);
        $smtpEncryption = SystemSetting::get('smtp_encryption', 'tls');
        $smtpUsername = SystemSetting::get('smtp_username');
        $smtpPassword = SystemSetting::get('smtp_password');
        $fromAddress = SystemSetting::get('smtp_from_address');
        $fromName = SystemSetting::get('smtp_from_name', 'IQOT');

        if (!$smtpHost || !$fromAddress) {
            throw new \Exception('SMTP настройки не заполнены. Проверьте настройки в разделе Settings.');
        }

        // Создаем транспорт
        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
            $smtpHost,
            $smtpPort,
            $smtpEncryption === 'ssl'
        );

        if ($smtpEncryption === 'tls') {
            $transport->setStreamOptions([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
        }

        if ($smtpUsername && $smtpPassword) {
            $transport->setUsername($smtpUsername);
            $transport->setPassword($smtpPassword);
        }

        // Создаем mailer с этим транспортом
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        // Отправляем письмо
        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
            ->to($to)
            ->subject($subject)
            ->html($html);

        // Встраиваем изображения как CID-вложения
        if ($campaign) {
            foreach ($campaign->images as $image) {
                $filePath = storage_path('app/public/' . $image->file_path);
                if (file_exists($filePath)) {
                    $email->embedFromPath($filePath, $image->cid);
                }
            }
        }

        \App\Support\Mail\TestMode::assertAllowed((string) $to);
        $mailer->send($email);
    }
}
