<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\EmailValidationService;
use Illuminate\Console\Command;

class ValidateCampaignEmails extends Command
{
    protected $signature = 'campaign:validate {campaign_id} {--provider=}';
    protected $description = 'Проверка валидности email адресов в рассылке';

    public function handle(EmailValidationService $validator)
    {
        $campaignId = $this->argument('campaign_id');
        $provider = $this->option('provider');

        $campaign = Campaign::find($campaignId);

        if (!$campaign) {
            $this->error('Рассылка не найдена');
            return 1;
        }

        $this->info("Проверяем email адреса для рассылки: {$campaign->name}");

        // Получаем получателей, которые еще не проверены
        $recipients = $campaign->recipients()
            ->where('email_validated', false)
            ->get();

        if ($recipients->isEmpty()) {
            $this->info('Все email адреса уже проверены');
            return 0;
        }

        $this->info("Всего адресов для проверки: {$recipients->count()}");

        $bar = $this->output->createProgressBar($recipients->count());
        $bar->start();

        $stats = [
            'valid' => 0,
            'invalid' => 0,
            'errors' => 0,
        ];

        foreach ($recipients as $recipient) {
            try {
                $result = $validator->validate($recipient->email, $provider);
                $recipient->markAsValidated($result);

                if ($result['valid']) {
                    $stats['valid']++;
                } else {
                    $stats['invalid']++;

                    // Помечаем невалидные как failed
                    $recipient->markAsFailed("Invalid email: {$result['reason']}");
                    $campaign->increment('failed_count');
                }

                $bar->advance();

                // Небольшая задержка между запросами к API
                if ($provider) {
                    usleep(100000); // 0.1 секунды
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->error("\nОшибка для {$recipient->email}: " . $e->getMessage());
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Проверка завершена!");
        $this->info("Валидных: {$stats['valid']}");
        $this->info("Невалидных: {$stats['invalid']}");

        if ($stats['errors'] > 0) {
            $this->warn("Ошибок: {$stats['errors']}");
        }

        return 0;
    }
}
