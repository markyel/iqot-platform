<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\N8nSenderService;
use Illuminate\Http\Request;

class UserSenderController extends Controller
{
    private N8nSenderService $senderService;

    public function __construct(N8nSenderService $senderService)
    {
        $this->senderService = $senderService;
    }

    /**
     * Показать информацию о Sender пользователя
     */
    public function show(User $user)
    {
        $senderData = $this->senderService->getUserSenderWithOrganization($user->id);

        // Автоматическая синхронизация: если sender существует, но не сохранён в User
        if (($senderData['sender'] ?? null) && !$user->sender_id) {
            $user->update([
                'sender_id' => $senderData['sender']['id'] ?? null,
                'client_organization_id' => $senderData['organization']['id'] ?? null,
            ]);
            $user->refresh();
        }

        // Проверяем конфигурацию
        $configError = null;
        if (!config('services.n8n.sender_webhook_url')) {
            $configError = 'N8N_SENDER_WEBHOOK_URL не настроен в .env';
        } elseif (!config('services.n8n.sender_auth_token')) {
            $configError = 'N8N_SENDER_AUTH_TOKEN не настроен в .env';
        }

        return view('admin.users.sender.show', [
            'user' => $user,
            'sender' => $senderData['sender'] ?? null,
            'organization' => $senderData['organization'] ?? null,
            'configError' => $configError,
            'apiError' => isset($senderData['error']) ? $senderData['message'] ?? 'Ошибка связи с API' : null,
        ]);
    }

    /**
     * Форма создания Sender
     */
    public function create(User $user)
    {
        $availableEmails = $this->senderService->getAvailableEmails();
        $templates = $this->senderService->getEmailTemplates();

        if (!($availableEmails['success'] ?? false)) {
            $errorMessage = 'Не удалось загрузить список email адресов. ';
            if (isset($availableEmails['message'])) {
                $errorMessage .= 'Ошибка: ' . $availableEmails['message'];
            }
            if (!config('services.n8n.sender_webhook_url')) {
                $errorMessage .= ' Проверьте переменную N8N_SENDER_WEBHOOK_URL в .env';
            }
            return back()->with('error', $errorMessage);
        }

        return view('admin.users.sender.create', [
            'user' => $user,
            'availableEmails' => $availableEmails['emails'] ?? [],
            'templates' => $templates['templates'] ?? [],
        ]);
    }

    /**
     * Создать Sender
     */
    public function store(Request $request, User $user)
    {
        $validated = $request->validate([
            'reserved_email_id' => 'required|integer',
            'template_id' => 'nullable|integer',
            'sender_name' => 'required|string|max:255',
            'sender_full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:100',
            'organization.name' => 'required|string|max:500',
            'organization.inn' => 'nullable|string|max:50',
            'organization.kpp' => 'nullable|string|max:50',
            'organization.legal_address' => 'nullable|string|max:1000',
            'organization.contact_person' => 'nullable|string|max:255',
            'organization.phone' => 'nullable|string|max:50',
            'organization.email' => 'nullable|email|max:255',
        ]);

        $result = $this->senderService->createSender($user->id, $validated);

        if ($result['success'] ?? false) {
            // Сохраняем ID в модель User
            $user->update([
                'sender_id' => $result['sender_id'],
                'client_organization_id' => $result['client_organization_id'],
            ]);

            return redirect()
                ->route('admin.users.sender.show', $user)
                ->with('success', 'Отправитель успешно создан');
        }

        return back()
            ->withInput()
            ->with('error', $result['message'] ?? 'Ошибка при создании отправителя');
    }

    /**
     * Форма редактирования Sender
     */
    public function edit(User $user)
    {
        $senderData = $this->senderService->getUserSenderWithOrganization($user->id);
        $templates = $this->senderService->getEmailTemplates();

        if (!($senderData['sender'] ?? null)) {
            return redirect()
                ->route('admin.users.sender.show', $user)
                ->with('error', 'Отправитель не найден');
        }

        return view('admin.users.sender.edit', [
            'user' => $user,
            'sender' => $senderData['sender'],
            'organization' => $senderData['organization'],
            'templates' => $templates['templates'] ?? [],
        ]);
    }

    /**
     * Обновить Sender
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'template_id' => 'nullable|integer',
            'sender_name' => 'required|string|max:255',
            'sender_full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:100',
            'organization.name' => 'required|string|max:500',
            'organization.inn' => 'nullable|string|max:50',
            'organization.kpp' => 'nullable|string|max:50',
            'organization.legal_address' => 'nullable|string|max:1000',
            'organization.contact_person' => 'nullable|string|max:255',
            'organization.phone' => 'nullable|string|max:50',
            'organization.email' => 'nullable|email|max:255',
        ]);

        $result = $this->senderService->updateSender($user->sender_id, $validated);

        if ($result['success'] ?? false) {
            return redirect()
                ->route('admin.users.sender.show', $user)
                ->with('success', 'Отправитель успешно обновлён');
        }

        return back()
            ->withInput()
            ->with('error', $result['message'] ?? 'Ошибка при обновлении');
    }

    /**
     * Деактивировать Sender
     */
    public function deactivate(User $user)
    {
        if (!$user->sender_id) {
            return back()->with('error', 'Отправитель не найден');
        }

        $result = $this->senderService->deactivateSender($user->sender_id);

        if ($result) {
            return redirect()
                ->route('admin.users.sender.show', $user)
                ->with('success', 'Отправитель деактивирован');
        }

        return back()->with('error', 'Ошибка при деактивации');
    }

    /**
     * Тестирование подключения к n8n API
     */
    public function testConnection()
    {
        $authToken = config('services.n8n.sender_auth_token');
        $webhookUrl = config('services.n8n.sender_webhook_url');

        $results = [
            'timestamp' => now()->toIso8601String(),
            'config' => [
                'webhook_url' => $webhookUrl,
                'auth_token_set' => !empty($authToken),
                'auth_token_preview' => $authToken
                    ? substr($authToken, 0, 20) . '...'
                    : 'not set',
                'is_placeholder_token' => str_contains($authToken ?? '', '__n8n_BLANK_VALUE'),
            ],
            'tests' => [],
            'recommendations' => []
        ];

        // Тест 0: Проверка доступности webhook (без авторизации)
        try {
            $pingResponse = \Illuminate\Support\Facades\Http::timeout(10)
                ->withoutVerifying() // На случай SSL проблем
                ->get($webhookUrl);

            $results['tests']['webhook_availability'] = [
                'success' => $pingResponse->status() !== 0,
                'status_code' => $pingResponse->status(),
                'message' => $pingResponse->status() === 404
                    ? 'Webhook не найден (404) - возможно workflow не активен или URL неверный'
                    : ($pingResponse->status() === 403
                        ? 'Webhook найден, но требует авторизацию (403) - это нормально'
                        : 'Status: ' . $pingResponse->status()),
                'response_preview' => substr($pingResponse->body(), 0, 200)
            ];
        } catch (\Exception $e) {
            $results['tests']['webhook_availability'] = [
                'success' => false,
                'message' => 'Не удалось подключиться к webhook: ' . $e->getMessage(),
                'error' => get_class($e)
            ];
        }

        // Тест 1: Получение списка email
        $emailsResult = $this->senderService->getAvailableEmails();
        $results['tests']['get_available_emails'] = [
            'success' => $emailsResult['success'] ?? false,
            'response' => $emailsResult
        ];

        // Тест 2: Получение списка шаблонов
        $templatesResult = $this->senderService->getEmailTemplates();
        $results['tests']['get_email_templates'] = [
            'success' => $templatesResult['success'] ?? false,
            'response' => $templatesResult
        ];

        // Анализ результатов и рекомендации
        $allSuccess = true;
        foreach ($results['tests'] as $test) {
            if (!($test['success'] ?? false)) {
                $allSuccess = false;
                break;
            }
        }

        if (!$allSuccess) {
            $results['recommendations'][] = '❌ Тесты не прошли. Диагностика проблемы:';

            // Проверка доступности webhook
            $webhookTest = $results['tests']['webhook_availability'] ?? null;
            if (!$webhookTest || !($webhookTest['success'] ?? false)) {
                $results['recommendations'][] = '🔴 ПРОБЛЕМА: Webhook недоступен!';
                $results['recommendations'][] = '   → Проверьте URL: ' . $webhookUrl;
                $results['recommendations'][] = '   → Убедитесь что n8n workflow активен';
                $results['recommendations'][] = '   → Проверьте правильность пути webhook';
            } elseif (($webhookTest['status_code'] ?? 0) === 404) {
                $results['recommendations'][] = '🔴 ПРОБЛЕМА: Webhook возвращает 404 (Not Found)';
                $results['recommendations'][] = '   → Проверьте URL webhook, возможно он неверный';
                $results['recommendations'][] = '   → Убедитесь что workflow в n8n активен';
                $results['recommendations'][] = '   → Правильный формат: https://liftway.app.n8n.cloud/webhook/НАЗВАНИЕ';
            } elseif (($webhookTest['status_code'] ?? 0) === 403) {
                $results['recommendations'][] = '🟡 Webhook доступен (HTTP 403 - требует авторизацию)';

                // Проверка placeholder токена
                if ($results['config']['is_placeholder_token']) {
                    $results['recommendations'][] = '🔴 ПРОБЛЕМА: Используется placeholder токен!';
                    $results['recommendations'][] = '   → Замените N8N_SENDER_AUTH_TOKEN в .env на реальный токен';
                    $results['recommendations'][] = '   → Токен должен совпадать с настройками Header Auth в n8n';
                } else {
                    $results['recommendations'][] = '🔴 ПРОБЛЕМА: Токен авторизации неверный';
                    $results['recommendations'][] = '   → Проверьте токен в n8n workflow (Webhook → Settings → Header Auth)';
                    $results['recommendations'][] = '   → Убедитесь что токен в .env совпадает с токеном в n8n';
                    $results['recommendations'][] = '   → Текущий токен начинается с: ' . ($authToken ? substr($authToken, 0, 20) : 'пусто');
                }
            }

            $results['recommendations'][] = '';
            $results['recommendations'][] = '📋 Дополнительные шаги:';
            $results['recommendations'][] = '1. Проверьте логи Laravel: storage/logs/laravel.log';
            $results['recommendations'][] = '2. Проверьте логи n8n для этого workflow';
            $results['recommendations'][] = '3. Попробуйте curl запрос (см. SENDER_SETUP.md)';
        } else {
            $results['recommendations'][] = '✅ Все тесты прошли успешно! Интеграция с n8n настроена корректно.';
        }

        return response()->json($results, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
