<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nQuestionsService
{
    protected ?string $baseUrl;
    protected ?string $authToken;

    public function __construct()
    {
        $this->baseUrl = config('services.n8n.webhook_url');
        $this->authToken = config('services.n8n.auth_token');
    }

    /**
     * Список вопросов
     */
    public function listQuestions(array $filters = [], array $sort = [], array $pagination = []): array
    {
        return $this->call([
            'action' => 'list_questions',
            'filters' => $filters,
            'sort' => $sort,
            'pagination' => $pagination
        ]);
    }

    /**
     * Получить вопрос
     */
    public function getQuestion(int $questionId): array
    {
        return $this->call([
            'action' => 'get_question',
            'question_id' => $questionId
        ]);
    }

    /**
     * Ответить на вопрос
     */
    public function answerQuestion(int $questionId, string $answer, array $files = [], ?int $userId = null): array
    {
        $data = [
            'action' => 'answer_question',
            'question_id' => $questionId,
            'answer' => $answer
        ];

        if (!empty($files)) {
            $data['files'] = $files;
        }

        if ($userId !== null) {
            $data['user_id'] = $userId;
        }

        return $this->call($data);
    }

    /**
     * Пропустить вопрос
     */
    public function skipQuestion(int $questionId, ?string $reason = null): array
    {
        $data = [
            'action' => 'skip_question',
            'question_id' => $questionId
        ];

        if ($reason !== null) {
            $data['reason'] = $reason;
        }

        return $this->call($data);
    }

    /**
     * Сводка по вопросам для списка заявок
     */
    public function getQuestionsSummary(array $requestIds = [], ?int $requestId = null): array
    {
        $data = ['action' => 'get_questions_summary'];

        if ($requestId !== null) {
            $data['request_id'] = $requestId;
        } elseif (!empty($requestIds)) {
            $data['request_ids'] = $requestIds;
        }

        return $this->call($data);
    }

    /**
     * Получить консолидированный список вопросов
     */
    public function getConsolidatedQuestions(int $userId, ?int $requestId = null): array
    {
        $data = [
            'action' => 'list_questions_consolidated',
            'user_id' => $userId
        ];

        if ($requestId !== null) {
            $data['request_id'] = $requestId;
        }

        return $this->call($data);
    }

    /**
     * Ответить на консолидированный вопрос (массовый ответ)
     */
    public function answerConsolidated(int $consolidationId, string $answer, array $files = [], ?int $userId = null): array
    {
        $data = [
            'action' => 'answer_question',
            'consolidation_id' => $consolidationId,
            'answer' => $answer
        ];

        if (!empty($files)) {
            $data['files'] = $files;
        }

        if ($userId !== null) {
            $data['user_id'] = $userId;
        }

        return $this->call($data);
    }

    /**
     * Выполнить запрос к n8n
     */
    protected function call(array $data): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->authToken) {
                $headers['X-Auth-Token'] = $this->authToken;
            }

            $response = Http::withHeaders($headers)
                ->timeout(60) // Увеличен таймаут до 60 сек для поддержки файлов
                ->post("{$this->baseUrl}/questions-management", $data);

            if ($response->successful()) {
                $jsonData = $response->json();
                if ($jsonData === null) {
                    Log::error("N8n questions response is not valid JSON", [
                        'action' => $data['action'] ?? 'unknown',
                        'body' => $response->body()
                    ]);
                    return [
                        'success' => false,
                        'error' => 'INVALID_JSON',
                        'message' => 'Получен некорректный JSON от n8n'
                    ];
                }
                return $jsonData;
            }

            Log::error("N8n questions request failed", [
                'action' => $data['action'] ?? 'unknown',
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'N8N_ERROR',
                'message' => 'Ошибка при обращении к n8n: ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error("N8n questions exception", [
                'action' => $data['action'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'CONNECTION_ERROR',
                'message' => 'Не удалось подключиться к n8n: ' . $e->getMessage()
            ];
        }
    }
}
