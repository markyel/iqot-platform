<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Services\N8nQuestionsService;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    private N8nQuestionsService $questionsService;

    public function __construct(N8nQuestionsService $questionsService)
    {
        $this->questionsService = $questionsService;
    }

    /**
     * Вопросы по заявке пользователя
     */
    public function requestQuestions(Request $request, $requestId)
    {
        // Проверяем что заявка принадлежит пользователю
        $userRequest = auth()->user()->requests()->find($requestId);

        if (!$userRequest) {
            abort(403, 'Доступ запрещен');
        }

        // Проверяем, что заявка синхронизирована с основной БД
        if (!$userRequest->synced_to_main_db || !$userRequest->main_db_request_id) {
            return view('cabinet.questions.index', [
                'questions' => [],
                'paginationData' => [
                    'total' => 0,
                    'page' => 1,
                    'per_page' => 20,
                    'total_pages' => 1
                ],
                'requestId' => $requestId,
                'userRequest' => $userRequest,
                'notSynced' => true
            ]);
        }

        // Используем main_db_request_id для получения вопросов из основной БД
        $filters = ['request_id' => (int)$userRequest->main_db_request_id];

        // Фильтр по умолчанию - только неотвеченные
        $filters['status'] = $request->filled('status')
            ? $request->status
            : ['pending', 'forwarded_to_author'];

        $sort = [
            'field' => 'created_at',
            'direction' => 'desc'
        ];

        $pagination = [
            'page' => $request->get('page', 1),
            'per_page' => 20
        ];

        $result = $this->questionsService->listQuestions($filters, $sort, $pagination);

        // n8n может возвращать массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        $questions = $result['questions'] ?? [];
        $paginationData = $result['pagination'] ?? [
            'total' => 0,
            'page' => 1,
            'per_page' => 20,
            'total_pages' => 1
        ];

        return view('cabinet.questions.index', compact('questions', 'paginationData', 'requestId', 'userRequest'));
    }

    /**
     * Ответить на вопрос
     */
    public function answer(Request $request, $questionId)
    {
        $validated = $request->validate([
            'answer' => 'required|string|min:3|max:2000',
            'request_id' => 'required|integer',
            'files' => 'nullable|array',
            'files.*' => 'file|max:10240' // 10MB
        ]);

        // Проверяем что заявка принадлежит пользователю
        $userRequest = auth()->user()->requests()->find($validated['request_id']);

        if (!$userRequest) {
            abort(403, 'Доступ запрещен');
        }

        // Подготовка файлов
        $files = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $files[] = [
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_type' => $this->detectFileType($file->getMimeType()),
                    'file_size' => $file->getSize(),
                    'content_base64' => base64_encode(file_get_contents($file->getRealPath())),
                ];
            }
        }

        $result = $this->questionsService->answerQuestion(
            (int)$questionId,
            $validated['answer'],
            $files,
            auth()->id()
        );

        // n8n может возвращать массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if ($result['success'] ?? false) {
            return back()->with('success', 'Ответ успешно отправлен');
        }

        return back()->with('error', $result['message'] ?? 'Ошибка при отправке ответа');
    }

    /**
     * Определить тип файла по MIME
     */
    private function detectFileType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'photo',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }
}
