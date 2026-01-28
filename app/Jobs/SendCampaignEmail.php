<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class SendCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 минуты на одно письмо
    public $tries = 3; // 3 попытки отправки
    public $backoff = 60; // Задержка между попытками 60 секунд

    protected int $recipientId;
    protected int $campaignId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $recipientId, int $campaignId)
    {
        $this->recipientId = $recipientId;
        $this->campaignId = $campaignId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $recipient = CampaignRecipient::find($this->recipientId);
        $campaign = Campaign::find($this->campaignId);

        if (!$recipient || !$campaign) {
            Log::warning('Campaign or recipient not found', [
                'recipient_id' => $this->recipientId,
                'campaign_id' => $this->campaignId
            ]);
            return;
        }

        // Пропускаем если уже отправлено или отписался
        if (in_array($recipient->status, ['sent', 'unsubscribed'])) {
            return;
        }

        try {
            // Получаем настройки SMTP
            $smtpHost = SystemSetting::get('smtp_host');
            $smtpPort = SystemSetting::get('smtp_port', 587);
            $smtpEncryption = SystemSetting::get('smtp_encryption', 'tls');
            $smtpUsername = SystemSetting::get('smtp_username');
            $smtpPassword = SystemSetting::get('smtp_password');
            $fromAddress = SystemSetting::get('smtp_from_address');
            $fromName = SystemSetting::get('smtp_from_name', 'IQOT');

            if (!$smtpHost || !$fromAddress) {
                throw new \Exception('SMTP не настроен в системе');
            }

            // Создаем транспорт
            $transport = new EsmtpTransport(
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

            $mailer = new Mailer($transport);

            // Рендерим HTML с подстановкой данных
            $html = $campaign->renderTemplate($recipient->data);

            // Заменяем URL отписки
            $unsubscribeUrl = route('campaign.unsubscribe', $recipient->id);
            $html = str_replace('__UNSUBSCRIBE_URL__', $unsubscribeUrl, $html);

            // Создаем письмо
            $email = (new Email())
                ->from(new Address($fromAddress, $fromName))
                ->to($recipient->email)
                ->subject($campaign->subject)
                ->html($html);

            // Встраиваем изображения как CID-вложения
            foreach ($campaign->images as $image) {
                $filePath = storage_path('app/public/' . $image->file_path);
                if (file_exists($filePath)) {
                    // CID должен быть в формате id@domain (требование Symfony)
                    $cid = $image->cid . '@iqot.ru';

                    $email->addPart(
                        (new DataPart(new File($filePath)))->asInline()->setContentId($cid)
                    );

                    // Заменяем src в HTML на cid:
                    $html = str_replace(
                        'src="' . $image->original_src . '"',
                        'src="cid:' . $cid . '"',
                        $html
                    );
                }
            }

            // Обновляем HTML с CID
            $email->html($html);

            // Отправляем
            $mailer->send($email);

            // Помечаем как отправленное
            $recipient->markAsSent();
            $campaign->increment('sent_count');

            Log::info('Campaign email sent', [
                'campaign_id' => $this->campaignId,
                'recipient_id' => $this->recipientId,
                'email' => $recipient->email
            ]);

        } catch (\Exception $e) {
            // Помечаем как ошибочное
            $recipient->markAsFailed($e->getMessage());
            $campaign->increment('failed_count');

            Log::error('Campaign email failed', [
                'campaign_id' => $this->campaignId,
                'recipient_id' => $this->recipientId,
                'email' => $recipient->email,
                'error' => $e->getMessage()
            ]);

            throw $e; // Пробросим для retry механизма
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Campaign email job failed after all retries', [
            'campaign_id' => $this->campaignId,
            'recipient_id' => $this->recipientId,
            'error' => $exception->getMessage()
        ]);
    }
}
