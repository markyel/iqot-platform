<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\N8nQuestionsService;
use Illuminate\Http\Request;

class ConsolidatedQuestionController extends Controller
{
    private N8nQuestionsService $questionsService;

    public function __construct(N8nQuestionsService $questionsService)
    {
        $this->questionsService = $questionsService;
    }

    /**
     * Консолидированный список вопросов
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        $requestId = $request->get('request_id');

        $result = $this->questionsService->getConsolidatedQuestions($userId, $requestId);

        // n8n может возвращать массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if (!($result['success'] ?? false)) {
            return view('admin.questions.consolidated', [
                'consolidatedQuestions' => [],
                'totalConsolidated' => 0,
                'totalOriginal' => 0,
                'byRequest' => [],
                'error' => $result['message'] ?? 'Ошибка получения консолидированных вопросов'
            ]);
        }

        // Данные могут быть как в result['data'], так и напрямую в result
        $consolidatedQuestions = $result['consolidated_questions'] ?? $result['data']['consolidated_questions'] ?? [];
        $totalConsolidated = $result['total'] ?? $result['data']['total_consolidated'] ?? count($consolidatedQuestions);

        // Вычисляем totalOriginal из данных, если не предоставлено
        $totalOriginal = $result['data']['total_original'] ?? 0;
        if ($totalOriginal === 0 && !empty($consolidatedQuestions)) {
            foreach ($consolidatedQuestions as $q) {
                $totalOriginal += $q['questions_count'] ?? count($q['original_question_ids'] ?? []);
            }
        }

        // Группируем по заявкам, если не предоставлено
        $byRequest = $result['data']['by_request'] ?? [];
        if (empty($byRequest) && !empty($consolidatedQuestions)) {
            foreach ($consolidatedQuestions as $q) {
                if (!empty($q['request_id'])) {
                    $byRequest[$q['request_id']] = $q['request_number'] ?? "REQ-{$q['request_id']}";
                }
            }
        }

        return view('admin.questions.consolidated', compact(
            'consolidatedQuestions',
            'totalConsolidated',
            'totalOriginal',
            'byRequest'
        ));
    }

    /**
     * Ответить на консолидированный вопрос
     */
    public function answer(Request $request)
    {
        $validated = $request->validate([
            'consolidation_id' => 'required|integer',
            'answer' => 'required|string|min:3|max:5000',
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

        $result = $this->questionsService->answerConsolidated(
            $validated['consolidation_id'],
            $validated['answer'],
            $files,
            auth()->id()
        );

        // n8n может возвращать массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if ($result['success'] ?? false) {
            $answeredCount = $result['answered_count'] ?? $result['data']['answered_count'] ?? 0;
            $filesAttached = $result['files_attached'] ?? 0;
            $message = "Ответ успешно отправлен {$answeredCount} поставщикам";
            if ($filesAttached > 0) {
                $message .= " с {$filesAttached} файлом(ами)";
            }
            return back()->with('success', $message);
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
