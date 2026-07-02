<?php

namespace App\Services\Senders;

/**
 * Мультиканальность релея (Phase 3c): диверсификация исходящего IP для рассылки
 * через beget. Репутация ОБЩЕГО релей-IP горит при всплеске (mail.ru спам-флаг на
 * все ящики сразу); лечится тем, что письма разных ящиков уходят с РАЗНЫХ внешних IP.
 *
 * Канал — способ egress'а: {host, port, source_ip, peer_name, weight}.
 *   - host/port  — куда коннектиться (релей-хост:порт). Пусто → smtp_server ящика
 *                  (текущий путь: smtp.beget.com через /etc/hosts → релей :8000).
 *   - source_ip  — локальный bind исходящего сокета (несколько внешних IP на 1 VDS,
 *                  прямой путь app→beget) ЛИБО egress-IP релея, если релей слушает
 *                  разные порты под разные исходящие IP (тогда лежит host+port).
 *   - peer_name  — TLS peer/SNI (при подмене host на IP: 'smtp.beget.com', чтобы
 *                  сертификат beget сошёлся — как в dual-path direct).
 *
 * ПРИВЯЗКА СТАБИЛЬНА per-sender (детерминированно по sender_id, с учётом weight):
 * каждый ящик всегда уходит с ОДНОГО IP → репутация копится когерентно на этом IP,
 * и нагрузка равномерно раскладывается по каналам. Случайный round-robin размазал бы
 * каждый ящик по всем IP — спам-флаг одного IP тогда бьёт по всем ящикам, изоляции нет.
 *
 * Применяется ТОЛЬКО к beget-ящикам (релей/направление — beget-специфичны, как
 * dual-path). Не-beget провайдеры (sprinthost и т.п.) шлют через свой smtp_server
 * без каналов. Пустой пул каналов → null (полный backward-compat, текущий путь).
 *
 * На каждый source_ip нужен свой rDNS/PTR + запись в SPF доменов-отправителей.
 */
class RelayChannelSelector
{
    /** @var array<int,array<string,mixed>> нормализованные каналы (weight>=1) */
    private array $channels;

    private int $totalWeight = 0;

    public function __construct(?array $channels = null)
    {
        $raw = $channels ?? (array) config('services.email_relays.channels', []);
        $this->channels = $this->normalize($raw);
        foreach ($this->channels as $c) {
            $this->totalWeight += (int) $c['weight'];
        }
    }

    public function hasChannels(): bool
    {
        return $this->channels !== [];
    }

    /**
     * Маршрут отправки для ящика: стабильный взвешенный выбор канала по sender_id.
     * Возвращает route-массив для *Sender::send() (host/port/peer_name/bindto) либо
     * null (каналов нет / ящик не beget → текущий путь).
     *
     * @return array{host?:string,port?:int,peer_name?:string,bindto?:string}|null
     */
    public function forSender(int $senderId, bool $isBeget): ?array
    {
        if (!$isBeget || $this->totalWeight <= 0 || $senderId <= 0) {
            return null;
        }

        $channel = $this->pick($senderId);
        if ($channel === null) {
            return null;
        }

        $route = [];
        if (($channel['host'] ?? '') !== '') {
            $route['host'] = (string) $channel['host'];
            // Подмена host на IP → TLS peer_name должен остаться smtp.beget.com,
            // иначе проверка сертификата beget не сойдётся (как в dual-path direct).
            $route['peer_name'] = (string) ($channel['peer_name'] ?? 'smtp.beget.com');
        } elseif (($channel['peer_name'] ?? '') !== '') {
            $route['peer_name'] = (string) $channel['peer_name'];
        }
        if ((int) ($channel['port'] ?? 0) > 0) {
            $route['port'] = (int) $channel['port'];
        }
        if (($channel['source_ip'] ?? '') !== '') {
            $route['bindto'] = (string) $channel['source_ip'];
        }

        return $route !== [] ? $route : null;
    }

    /**
     * Детерминированный взвешенный выбор канала по sender_id: индекс = sender_id по
     * модулю суммарного веса, затем разворачиваем в конкретный канал по кумулятиву.
     *
     * @return array<string,mixed>|null
     */
    private function pick(int $senderId): ?array
    {
        $slot = $senderId % $this->totalWeight;
        $acc = 0;
        foreach ($this->channels as $c) {
            $acc += (int) $c['weight'];
            if ($slot < $acc) {
                return $c;
            }
        }
        return $this->channels[0] ?? null;
    }

    /**
     * @param array<int,mixed> $raw
     * @return array<int,array<string,mixed>>
     */
    private function normalize(array $raw): array
    {
        $out = [];
        foreach ($raw as $c) {
            if (!is_array($c)) {
                continue;
            }
            $host = trim((string) ($c['host'] ?? ''));
            $sourceIp = trim((string) ($c['source_ip'] ?? ''));
            $peerName = trim((string) ($c['peer_name'] ?? ''));
            // Пустой канал (ни host, ни source_ip, ни peer_name) бессмыслен — пропуск.
            if ($host === '' && $sourceIp === '' && $peerName === '') {
                continue;
            }
            $out[] = [
                'host' => $host,
                'port' => (int) ($c['port'] ?? 0),
                'source_ip' => $sourceIp,
                'peer_name' => $peerName,
                'weight' => max(1, (int) ($c['weight'] ?? 1)),
            ];
        }
        return $out;
    }
}
