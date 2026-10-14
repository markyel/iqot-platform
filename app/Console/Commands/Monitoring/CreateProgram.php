<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Api\ApiClient;
use App\Models\Monitoring\MonitoringProgram;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Создать программу мониторинга (служебно, напр. для пилота). Обычный путь — API
 * POST /v1/monitoring/programs.
 */
class CreateProgram extends Command
{
    protected $signature = 'monitoring:program-create {user : id пользователя} {name} {--api-client= : id api_client (по умолчанию — клиент пользователя)} {--active}';

    protected $description = 'Мониторинг цен: создать программу мониторинга';

    public function handle(): int
    {
        $user = User::find((int) $this->argument('user'));
        if (!$user) {
            $this->error('User not found.');

            return self::FAILURE;
        }
        $clientId = $this->option('api-client') ?: ApiClient::query()->where('user_id', $user->id)->value('id');

        $program = MonitoringProgram::create([
            'user_id' => $user->id,
            'api_client_id' => $clientId ? (int) $clientId : null,
            'name' => (string) $this->argument('name'),
            'status' => $this->option('active') ? MonitoringProgram::STATUS_ACTIVE : MonitoringProgram::STATUS_DRAFT,
        ]);

        $this->info("Program #{$program->id} mon_{$program->external_id} created (status {$program->status}).");

        return self::SUCCESS;
    }
}
