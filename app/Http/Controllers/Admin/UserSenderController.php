<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Senders\CustomerSenderService;
use Illuminate\Http\Request;

class UserSenderController extends Controller
{
    private CustomerSenderService $senderService;

    public function __construct(CustomerSenderService $senderService)
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

        $configError = null;

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
     * Состояние пула ящиков для клиентских отправителей (раньше — проверка n8n sender-webhook).
     */
    public function testConnection()
    {
        $emails = $this->senderService->getAvailableEmails();
        $templates = $this->senderService->getEmailTemplates();

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'free_reserved_emails' => count($emails['emails'] ?? []),
            'email_templates' => count($templates['templates'] ?? []),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
