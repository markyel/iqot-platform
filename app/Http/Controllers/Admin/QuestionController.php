<?php

namespace App\Http\Controllers\Admin;

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
     * Список всех вопросов
     */
    public function index(Request $request)
    {
        $filters = [];

        // Фильтр по умолчанию - только forwarded_to_author (переданные автору)
        $filters['status'] = $request->filled('status')
            ? $request->status
            : 'forwarded_to_author';

        if ($request->filled('request_number')) {
            $filters['request_number'] = $request->request_number;
        }

        if ($request->filled('supplier_id')) {
            $filters['supplier_id'] = $request->supplier_id;
        }

        if ($request->filled('priority')) {
            $filters['priority'] = $request->priority;
        }

        if ($request->filled('question_type')) {
            $filters['question_type'] = $request->question_type;
        }

        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }

        $sort = [
            'field' => $request->get('sort_field', 'created_at'),
            'direction' => $request->get('sort_direction', 'desc')
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

        return view('admin.questions.index', compact('questions', 'paginationData', 'filters'));
    }

    /**
     * Вопросы по конкретной заявке
     */
    public function requestQuestions(Request $request, $requestId)
    {
        $filters = ['request_id' => (int)$requestId];

        // Фильтр по умолчанию - только forwarded_to_author
        $filters['status'] = $request->filled('status')
            ? $request->status
            : 'forwarded_to_author';

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

        return view('admin.questions.request', compact('questions', 'paginationData', 'requestId'));
    }

    /**
     * Ответить на вопрос
     */
    public function answer(Request $request, $questionId)
    {
        $validated = $request->validate([
            'answer' => 'required|string|min:3|max:2000',
            'files' => 'nullable|array',
            'files.*' => 'file|max:10240' // 10MB
        ]);

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

    /**
     * Пропустить вопрос
     */
    public function skip(Request $request, $questionId)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        $result = $this->questionsService->skipQuestion(
            (int)$questionId,
            $validated['reason'] ?? null
        );

        // n8n может возвращать массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if ($result['success'] ?? false) {
            return back()->with('success', 'Вопрос пропущен');
        }

        return back()->with('error', $result['message'] ?? 'Ошибка при пропуске вопроса');
    }
}
