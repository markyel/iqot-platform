<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Date;

/**
 * Синхронная валидация POST /api/v1/submissions (§11.2 / §12.1).
 */
class CreateSubmissionRequest extends FormRequest
{
    /**
     * Лимиты.
     * HARD limit на items — 300 (§12.1). Soft 100 — через warning header, реализуем в контроллере.
     */
    public const ITEMS_HARD_LIMIT = 300;
    public const DEADLINE_MIN_DAYS = 7;
    public const DEADLINE_MAX_DAYS = 60;
    public const DEFAULT_DEADLINE_DAYS = 30;

    public function authorize(): bool
    {
        // Авторизация уже выполнена middleware api.auth.
        return true;
    }

    /**
     * Явно используем JSON body для валидации.
     * Без этого в Laravel 11 на некоторых конфигурациях FormRequest теряет
     * данные из application/json body.
     */
    public function validationData(): array
    {
        if ($this->isJson()) {
            return array_merge($this->query(), $this->json()->all());
        }
        return $this->all();
    }

    public function rules(): array
    {
        return [
            'client_ref' => 'nullable|string|max:128',
            'client_organization_id' => 'nullable|integer|min:1',
            'deadline' => 'nullable|date',

            'items' => 'required|array|min:1|max:' . self::ITEMS_HARD_LIMIT,
            'items.*.client_ref' => 'nullable|string|max:128',
            'items.*.name' => 'required|string|min:1|max:500',
            'items.*.article' => 'nullable|string|max:255',
            'items.*.brand' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit' => 'required|string|min:1|max:32',
            'items.*.description' => 'nullable|string|max:5000',

            'items.*.client_category' => 'nullable|array',
            'items.*.client_category.code' => 'required_with:items.*.client_category|string|max:128',
            'items.*.client_category.path' => 'nullable|array|max:10',
            'items.*.client_category.path.*' => 'string|max:255',
            'items.*.client_category.metadata' => 'nullable|array',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $deadline = $this->input('deadline');
            if ($deadline) {
                try {
                    $dl = Date::parse($deadline);
                    $now = now();
                    $diffDays = $now->diffInDays($dl, false);
                    if ($diffDays < self::DEADLINE_MIN_DAYS || $diffDays > self::DEADLINE_MAX_DAYS) {
                        $v->errors()->add(
                            'deadline',
                            sprintf(
                                'Deadline must be between %d and %d days from now.',
                                self::DEADLINE_MIN_DAYS, self::DEADLINE_MAX_DAYS
                            )
                        );
                    }
                } catch (\Throwable $e) {
                    $v->errors()->add('deadline', 'Invalid deadline format. Use ISO 8601.');
                }
            }
        });
    }

    /**
     * Подготавливает нормализованный payload для SubmissionService::create().
     * Добавляет deadline_at по умолчанию если не указан.
     */
    public function toPayload(): array
    {
        $data = $this->validated();

        $deadline = $data['deadline'] ?? null;
        $deadlineAt = $deadline
            ? Date::parse($deadline)->utc()
            : now()->addDays(self::DEFAULT_DEADLINE_DAYS);

        return [
            'client_ref' => $data['client_ref'] ?? null,
            'client_organization_id' => isset($data['client_organization_id'])
                ? (int) $data['client_organization_id']
                : null,
            'deadline_at' => $deadlineAt,
            'items' => array_values($data['items']),
        ];
    }

    /**
     * Кастомный ответ 400 в формате API-спеки (§14.1).
     */
    protected function failedValidation(Validator $validator): void
    {
        $details = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $details[] = ['field' => $field, 'message' => $message];
            }
        }

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'invalid_payload',
                'message' => 'Request payload failed validation.',
                'details' => $details,
                'request_id' => $this->attributes->get('api_request_id'),
            ],
        ], 400));
    }
}
