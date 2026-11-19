<?php

namespace Tests\Unit;

use App\Services\Api\OpenAIClassifierClient;
use Tests\TestCase;

class AiClientTest extends TestCase
{
    private function client(): OpenAIClassifierClient
    {
        return new OpenAIClassifierClient('https://routerai.ru/api/v1', 'k', '', 'gpt-4o-mini', 'gpt-4o', 30);
    }

    public function test_decode_plain_json(): void
    {
        $this->assertSame(['price' => 12500], OpenAIClassifierClient::decodeJson('{"price": 12500}'));
    }

    public function test_decode_fenced_and_thinking(): void
    {
        $this->assertSame(['a' => 1], OpenAIClassifierClient::decodeJson("```json\n{\"a\": 1}\n```"));
        $this->assertSame(['a' => 1], OpenAIClassifierClient::decodeJson("<think>сначала цена…</think>\n{\"a\": 1}"));
        $this->assertSame(['a' => 1], OpenAIClassifierClient::decodeJson('Вот ответ: {"a": 1} — готово'));
    }

    public function test_decode_garbage_is_null(): void
    {
        $this->assertNull(OpenAIClassifierClient::decodeJson('нет JSON'));
    }

    public function test_model_resolution_order(): void
    {
        config(['services.openai_classifier.by_caller' => ['AiClientTest' => 'google/gemini-x']]);
        config(['services.openai_classifier.models' => ['gpt-4o' => 'qwen/big']]);
        config(['services.openai_classifier.default_model' => 'qwen/qwen3.8-27b']);
        $this->assertSame('google/gemini-x', $this->client()->resolveModel('gpt-4o'), 'задача важнее модели');

        config(['services.openai_classifier.by_caller' => []]);
        $this->assertSame('qwen/big', $this->client()->resolveModel('gpt-4o'));
        $this->assertSame('qwen/qwen3.8-27b', $this->client()->resolveModel('gpt-4o-mini'));

        config(['services.openai_classifier.default_model' => '', 'services.openai_classifier.models' => []]);
        $this->assertSame('gpt-4o-mini', $this->client()->resolveModel('gpt-4o-mini'), 'без настроек — как есть');
    }
}
