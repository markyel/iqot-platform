<?php

namespace App\Services\Senders;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Загрузка вложений в Google Drive на время переходного периода.
 *
 * Зачем: downstream-воркфлоу n8n «Process Email Conversations» читает
 * email_attachments.file_path и ждёт там Google-Drive-URL вида
 * .../d/{fileId}/... (нода «Extract File ID»), затем копирует файл в Google Doc и
 * выгружает текст для AI. Пока приём почты переехал в Laravel (вложения теперь на
 * локальном диске), file_path стал локальным путём → downstream ломается. Поэтому
 * на переходный период дублируем вложение в Drive и кладём Drive-URL в file_path
 * (см. IncomingEmailRouter::storeAttachments). Локальная копия остаётся источником
 * истины (local_path); после правки downstream Drive-дублирование выключается.
 *
 * Аутентификация — OAuth2 refresh-token пользователя liftway.ru. Service Account не
 * подошёл: орг-политика `iam.disableServiceAccountKeyCreation` запрещает ключи SA.
 * OAuth-приложение на домене делается типом Internal → refresh-token не протухает.
 * Файлы владеет пользователь (его квота 180 ГБ), льются в folder_id (PQSFiles) этого
 * же аккаунта → downstream-копирование имеет доступ без публичных прав.
 */
class GoogleDriveUploader
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true';
    private const TOKEN_CACHE_KEY = 'gdrive:access_token';

    /**
     * Включено и сконфигурировано (флаг + OAuth-креды + папка).
     */
    public function isEnabled(): bool
    {
        $c = (array) config('services.attachments_drive');

        return (bool) ($c['enabled'] ?? false)
            && !empty($c['client_id'])
            && !empty($c['client_secret'])
            && !empty($c['refresh_token'])
            && !empty($c['folder_id']);
    }

    /**
     * Заливает содержимое в Drive-папку и возвращает view-URL
     * (https://drive.google.com/file/d/{id}/view) или null при любой ошибке —
     * вызывающая сторона тогда оставляет в file_path локальный путь.
     */
    public function upload(string $name, string $content, string $mime): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $token = $this->accessToken();
            if ($token === null) {
                return null;
            }

            $folderId = (string) config('services.attachments_drive.folder_id');
            $mime = $mime !== '' ? $mime : 'application/octet-stream';

            $metadata = json_encode(
                ['name' => $name, 'parents' => [$folderId]],
                JSON_UNESCAPED_UNICODE
            );

            // multipart/related (метаданные + media) одним запросом — стандартный
            // формат Google Drive uploadType=multipart.
            $boundary = 'iqot_' . bin2hex(random_bytes(8));
            $body = "--{$boundary}\r\n"
                . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
                . $metadata . "\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: {$mime}\r\n\r\n"
                . $content . "\r\n"
                . "--{$boundary}--";

            $response = Http::withToken($token)
                ->withBody($body, "multipart/related; boundary={$boundary}")
                ->timeout($this->timeout())
                ->post(self::UPLOAD_URL);

            if (!$response->successful()) {
                Log::warning('GoogleDrive upload failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $id = (string) $response->json('id', '');
            if ($id === '') {
                return null;
            }

            return "https://drive.google.com/file/d/{$id}/view";
        } catch (\Throwable $e) {
            Log::warning('GoogleDrive upload exception', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Access-token по refresh-token, кэшируется до истечения (минус минута).
     */
    private function accessToken(): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $c = (array) config('services.attachments_drive');

        $response = Http::asForm()
            ->timeout($this->timeout())
            ->post(self::TOKEN_URL, [
                'client_id' => $c['client_id'],
                'client_secret' => $c['client_secret'],
                'refresh_token' => $c['refresh_token'],
                'grant_type' => 'refresh_token',
            ]);

        if (!$response->successful()) {
            Log::warning('GoogleDrive token refresh failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            return null;
        }

        $expiresIn = (int) $response->json('expires_in', 3600);
        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds(max(60, $expiresIn - 60)));

        return $token;
    }

    private function timeout(): int
    {
        return (int) config('services.attachments_drive.timeout', 30);
    }
}
