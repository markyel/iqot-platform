<?php

namespace Tests\Unit;

use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Свой шлюз основным, RouterAI резервом (25.11.2026). */
class AiGatewayFallbackTest extends TestCase
{
    private const GW = 'https://llm.liftway.ru/v1';

    private const RA = 'https://routerai.ru/api/v1';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'services.openai_classifier.by_caller' => [],
            'services.openai_classifier.models' => [],
            'services.openai_classifier.default_model' => 'local/qwen3.8-27b',
            'services.openai_classifier.min_max_tokens' => 1500,
            'services.openai_classifier.primary_concurrency' => 0,
            'services.openai_classifier.backup' => ['enabled' => true, 'base_url' => self::RA, 'api_key' => 'rk',
                'models' => '', 'reasoning_effort' => 'low', 'default_model' => 'qwen/qwen3.8-27b'],
        ]);
        Cache::forget('ai:primary:down');
    }

    private function client(): OpenAIClassifierClient
    {
        return new OpenAIClassifierClient(self::GW, 'gk', '', 'gpt-4o-mini', 'gpt-4o', 120);
    }

    private static function ok(string $content): array
    {
        return ['choices' => [['message' => ['content' => $content]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]];
    }

    public function test_gateway_answers(): void
    {
        Http::fake([self::GW . '/*' => Http::response(self::ok('{"a":1}')), self::RA . '/*' => Http::response(self::ok('{"a":2}'))]);

        $this->assertSame(['a' => 1], $this->client()->jsonCompletion('gpt-4o', 's', 'u'));
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), self::GW) && $r['model'] === 'local/qwen3.8-27b');
        Http::assertNotSent(fn (Request $r) => str_starts_with($r->url(), self::RA));
    }

    public function test_gateway_down_goes_to_routerai_and_marks_primary_down(): void
    {
        Http::fake([self::GW . '/*' => Http::response('model offline', 503), self::RA . '/*' => Http::response(self::ok('{"a":2}'))]);

        $this->assertSame(['a' => 2], $this->client()->jsonCompletion('gpt-4o', 's', 'u'));
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), self::RA) && $r['model'] === 'qwen/qwen3.8-27b'
            && $r['reasoning'] === ['enabled' => false] && !isset($r['reasoning_effort']));
        $this->assertTrue((bool) Cache::get('ai:primary:down'));
    }

    public function test_local_model_4xx_goes_to_routerai(): void
    {
        Http::fake([self::GW . '/*' => Http::response(['error' => 'model not loaded'], 404), self::RA . '/*' => Http::response(self::ok('{"a":3}'))]);

        $this->assertSame(['a' => 3], $this->client()->jsonCompletion('gpt-4o', 's', 'u'));
    }

    public function test_empty_answer_retried_via_routerai(): void
    {
        Http::fake([self::GW . '/*' => Http::response(self::ok('')), self::RA . '/*' => Http::response(self::ok('{"a":4}'))]);

        $this->assertSame(['a' => 4], $this->client()->jsonCompletion('gpt-4o', 's', 'u'));
        $this->assertFalse((bool) Cache::get('ai:primary:down'), 'пустой ответ — не признак недоступности');
    }

    public function test_busy_gateway_goes_to_routerai(): void
    {
        config(['services.openai_classifier.primary_concurrency' => 1]);
        $held = Cache::lock('ai:primary:slot:0', 300);
        $this->assertTrue($held->get());
        Http::fake([self::GW . '/*' => Http::response(self::ok('{"a":1}')), self::RA . '/*' => Http::response(self::ok('{"a":5}'))]);

        $this->assertSame(['a' => 5], $this->client()->jsonCompletion('gpt-4o', 's', 'u'));
        Http::assertNotSent(fn (Request $r) => str_starts_with($r->url(), self::GW));
        $held->release();
    }
}
