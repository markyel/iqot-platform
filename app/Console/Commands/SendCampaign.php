<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\SystemSetting;
use Illuminate\Console\Command;

class SendCampaign extends Command
{
    protected $signature = 'campaign:send {campaign_id}';
    protected $description = 'Отправка email рассылки';

    public function handle()
    {
        $campaignId = $this->argument('campaign_id');
        $campaign = Campaign::find($campaignId);

        if (!$campaign) {
            $this->error('Рассылка не найдена');
            return 1;
        }

        if ($campaign->status !== 'sending') {
            $this->error('Рассылка не в статусе отправки');
            return 1;
        }

        $this->info("Начинаем рассылку: {$campaign->name}");
        $this->info("Всего получателей: {$campaign->total_recipients}");
        $this->info("Задержка между письмами: {$campaign->delay_seconds} сек");

        // Получаем настройки SMTP
        $smtpHost = SystemSetting::get('smtp_host');
        $smtpPort = SystemSetting::get('smtp_port', 587);
        $smtpEncryption = SystemSetting::get('smtp_encryption', 'tls');
        $smtpUsername = SystemSetting::get('smtp_username');
        $smtpPassword = SystemSetting::get('smtp_password');
        $fromAddress = SystemSetting::get('smtp_from_address');
        $fromName = SystemSetting::get('smtp_from_name', 'IQOT');

        if (!$smtpHost || !$fromAddress) {
            $this->error('SMTP не настроен в системе');
            $campaign->update(['status' => 'failed']);
            return 1;
        }

        // Создаем транспорт
        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
            $smtpHost,
            $smtpPort,
            $smtpEncryption === 'ssl'
        );

        if ($smtpEncryption === 'tls') {
            $transport->setStreamOptions([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
        }

        if ($smtpUsername && $smtpPassword) {
            $transport->setUsername($smtpUsername);
            $transport->setPassword($smtpPassword);
        }

        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        $recipients = $campaign->recipients()->where('status', 'pending')->get();
        $bar = $this->output->createProgressBar(count($recipients));
        $bar->start();

        foreach ($recipients as $recipient) {
            try {
                // Рендерим HTML с подстановкой данных
                $html = $campaign->renderTemplate($recipient->data);

                // Отправляем письмо
                $email = (new \Symfony\Component\Mime\Email())
                    ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                    ->to($recipient->email)
                    ->subject($campaign->subject)
                    ->html($html);

                $mailer->send($email);

                // Помечаем как отправленное
                $recipient->markAsSent();
                $campaign->increment('sent_count');

                $bar->advance();

                // Задержка между письмами
                if ($campaign->delay_seconds > 0) {
                    sleep($campaign->delay_seconds);
                }
            } catch (\Exception $e) {
                // Помечаем как ошибочное
                $recipient->markAsFailed($e->getMessage());
                $campaign->increment('failed_count');

                $this->error("\nОшибка для {$recipient->email}: " . $e->getMessage());
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Обновляем статус рассылки
        $campaign->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->info("Рассылка завершена!");
        $this->info("Отправлено: {$campaign->sent_count}");
        $this->info("Ошибок: {$campaign->failed_count}");

        return 0;
    }
}
