<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class DemoRequestController extends Controller
{
    /**
     * Список заявок на демонстрацию
     */
    public function index()
    {
        $requests = DemoRequest::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.demo-requests.index', compact('requests'));
    }

    /**
     * Просмотр заявки
     */
    public function show(DemoRequest $demoRequest)
    {
        return view('admin.demo-requests.show', compact('demoRequest'));
    }

    /**
     * Одобрить заявку и создать пользователя
     */
    public function approve(DemoRequest $demoRequest)
    {
        if ($demoRequest->status !== 'new') {
            return back()->with('error', 'Заявка уже обработана');
        }

        // Проверяем, существует ли пользователь с таким email
        $user = User::where('email', $demoRequest->email)->first();

        if (!$user) {
            // Создаём нового пользователя
            $temporaryPassword = Str::random(16);

            $user = User::create([
                'name' => $demoRequest->full_name,
                'email' => $demoRequest->email,
                'password' => Hash::make($temporaryPassword),
                'organization' => $demoRequest->organization,
                'inn' => $demoRequest->inn,
                'kpp' => $demoRequest->kpp,
                'phone' => $demoRequest->phone,
                'email_verified_at' => now(),
            ]);

            // TODO: Отправить письмо с предложением установить пароль
            // Mail::to($user->email)->send(new SetPasswordMail($user, $temporaryPassword));
        }

        // Обновляем статус заявки
        $demoRequest->update([
            'status' => 'contacted',
            'notes' => 'Заявка одобрена. Пользователь создан: ' . $user->email,
        ]);

        return back()->with('success', 'Заявка одобрена. ' . ($user->wasRecentlyCreated ? 'Создан новый пользователь.' : 'Связан с существующим пользователем.'));
    }

    /**
     * Отклонить заявку
     */
    public function reject(Request $request, DemoRequest $demoRequest)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $demoRequest->update([
            'status' => 'completed',
            'notes' => 'Отклонено: ' . $request->reason,
        ]);

        return back()->with('success', 'Заявка отклонена');
    }

    /**
     * Добавить заметку к заявке
     */
    public function addNote(Request $request, DemoRequest $demoRequest)
    {
        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $currentNotes = $demoRequest->notes ?? '';
        $newNote = now()->format('Y-m-d H:i') . ': ' . $request->note;

        $demoRequest->update([
            'notes' => $currentNotes ? $currentNotes . "\n" . $newNote : $newNote,
        ]);

        return back()->with('success', 'Заметка добавлена');
    }

    /**
     * Изменить статус заявки
     */
    public function updateStatus(Request $request, DemoRequest $demoRequest)
    {
        $request->validate([
            'status' => 'required|in:new,processing,contacted,completed',
        ]);

        $demoRequest->update([
            'status' => $request->status,
        ]);

        return back()->with('success', 'Статус обновлён');
    }
}
