# Экспериментальный алгоритм «ящик на батч» (без меток)

Статус: Ф1–Ф4 на проде (16.10.2026), флаг `ALIAS_EXP_ENABLED` выключен; пробный батч 53278 (makarov.k@prosnabz.ru, 115 писем) удержан до решения владельца. Работает **параллельно** основной рассылке (план дня v2), не заменяет её.

## Идея (владелец, 16.10.2026)
Метки (токен в теме, видимый/скрытый номер в теле) не нужны: **ящик и есть метка**. На каждый батч создаётся
настоящий ящик, вся его входящая почта пересылается в общий `info@` домена; ответ опознаётся по адресу «Кому»
(`X-Original-To`), поставщик — по адресу отправителя ответа, точное письмо — по `In-Reply-To`.

## Проверено на проде (16.10.2026, домен stroy-lift.ru)
- Beget **не даёт** слать от несуществующего адреса: `550 You can send from only local domains and existing mailboxes`.
- API Beget (`api.beget.com/api/mail/*`): `createMailbox`, `forwardListAddMailbox`, `changeMailboxSettings`
  (`forward_mail_status=forward_and_delete` — только вместе с `spam_filter_status` и `spam_filter`, иначе INVALID_DATA),
  `dropMailbox`, `setDomainMail` (catch-all домена), `getMailboxList` — работают.
- Ящик, созданный через API минуту назад, отправляет; письмо пришло во «Входящие» Яндекса.
- Ответ на ящик-метку пришёл в общий ящик с `To`/`X-Original-To: ivan.petrov@…`, `Delivered-To: snab@…`,
  `In-Reply-To: <message-id нашего письма>`. Письмо на несуществующий адрес домена — тоже (catch-all).
  ⇒ после удаления ящика поздние ответы не теряются: catch-all доставит их в `info@`, адрес укажет на батч.

- **Сквозной тест на боевой схеме (16.10.2026, prosnabz.ru):** ящик `natalya.makarova@` создан фабрикой (персона, телефон легенды,
  пересылка → `info@`), письмо ушло через релей на ящик владельца без меток; ответ лёг в `info@prosnabz.ru` с
  `To`/`X-Original-To: natalya.makarova@prosnabz.ru`, `Delivered-To: info@`, `In-Reply-To` = message-id нашего письма.
  Служебное письмо Beget (welcome@beget.com) приходит без `X-Original-To` — приём его просто пропустит.

## Решения владельца
| Параметр | Решение |
|---|---|
| Батч | 1–2 позиции, близкие по тематике (тот же тип товара; однотипные — `HomogeneousKey`) |
| Получатели | все подходящие поставщики пула (`CampaignSupplierSelector`) — хоть 50, хоть 1000 |
| Темп | **1 письмо в 5 минут** с ящика (`send_delay_seconds=300`), только будни **9:00–18:00 МСК** (уже окна основной рассылки) |
| Доля | 20% позиций (именные заявки клиентов — в основной ветке, у них персональные отправители) |
| Организации | резерв загружается в базу; для нового ящика — **самая давно использованная**, повторно можно |
| Срок жизни ящика | дедлайн заявки + 7 дней; без дедлайна — 21 день; затем `dropMailbox` |
| Домены | новые, отдельный пул под алгоритм |
| Ящиков в день | ≤ ~10 (при темпе 1/5 мин ящик отправляет ~100 писем за день) |

## Архитектура
1. **Резерв организаций** (БД iqot, `alias_org_reserve`) — загрузка Excel ExportBase на `/manage/senders/import`
   (вкладка «Резерв организаций»); upsert по ИНН; `uses`, `last_used_at`; выбор — LRU, свободные от живых ящиков вперёд.
2. **Пул доменов** (`alias_domains`) — домен, общий ящик `info@` (IMAP-креды), catch-all через `setDomainMail`.
3. **Сервис API Beget** (`BegetMailApi`) — создание/удаление ящика, пересылка, catch-all. Ключи — `BEGET_API_LOGIN` /
   `BEGET_API_PASSWORD` в `.env` прода (кладёт владелец).
4. **Фабрика ящика под батч** — орг. из резерва → `client_organizations` (как импорт) → персона (`SenderAddressGenerator`)
   → `createMailbox` + пересылка на `info@` → строка `senders` (пул `alias`, `send_delay_seconds=300`, шаблон/тон —
   случайные из сильных) → карта `alias_mailboxes` (адрес → батч, срок жизни).
5. **Ветка в плане дня** — 20% новых позиций уходят в ветку; батчи по 1–2 позиции; письма всем поставщикам пула в
   `email_queue` с ящика батча. Отправку и паузы держит существующий диспетчер (`reserveSlot`, окно будних рабочих
   часов, капы/интервал получателя — общие с основной веткой). Без меток: блок `token` и `{{tracking_token}}` пустые.
   Ящики пула `alias` исключены из основной ветки (план дня, назначение отправителей, возврат/прогрев).
6. **Приём** — читаем только `info@` доменов; `IncomingEmailRouter`: адрес «Кому»/`X-Original-To` ∈ `alias_mailboxes`
   → батч; поставщик — по адресу отправителя; точное письмо — `In-Reply-To` = message-id отправленного письма.
7. **Удаление** — ежедневно: ящики с истёкшим сроком → `dropMailbox`, `senders.is_active=0`; карта адресов остаётся.
8. **Сравнение веток** — в админке «Ход выполнения → Охват поставщиков»: ответы, КП, спам-отказы по веткам.

## Фазы
- **Ф1** — резерв организаций (таблица, загрузка, статистика). Основную рассылку не затрагивает.
- Ф2 — пул доменов + `BegetMailApi` + фабрика ящика (ручной прогон).
- Ф3 — ветка в плане дня (флаг `ALIAS_EXP_ENABLED`, доля 20%), отправка без меток.
- Ф4 — приём через `info@` и опознание по адресу.
- Ф5 — удаление по сроку; сравнение веток в админке.

## Сделано
- **Ф1** — `alias_org_reserve` (БД iqot), `App\Services\AliasExp\OrgReserve`, вкладка «Резерв организаций» на `/manage/senders/import`.
- **Ф2** — `alias_domains` / `alias_mailboxes` (БД iqot), `BegetMailApi` (POST на `api.beget.com/api/mail/*`, ключи
  `BEGET_API_LOGIN`/`BEGET_API_PASSWORD` в `.env` прода с 16.10.2026), `AliasMailboxFactory` (откат ящика в Beget при любом сбое
  после создания), `App\Support\Senders\SenderPool` — фильтр «только основная рассылка» в плане дня (`senderCaps`),
  `CampaignSenderAssigner`, `SenderDailyCapacity::poolRemainingTotal`, проверочных письмах Гибрида, прогреве, возврате ящиков и
  опросе IMAP. Спам-гвард ящики ветки не исключает (защищает репутацию доменов). Без колонки `senders.pool` фильтр — no-op,
  а фабрика ящик не создаёт. Колонка `reports.senders.pool` (`main`/`alias`, индекс) — накачена 16.10.2026 (подтверждение владельца).
- **Ф3** — `App\Services\AliasExp\AliasBranch` в `emails:plan-day` (шаг 2c, до аффинности): доля по `crc32("alias:".item_id)`, только
  неименные и ещё никому не отправленные позиции; позиции из `alias_mailboxes.request_item_ids` основная ветка (plan-day,
  build-intents, plan-render) больше не берёт; не влезшие в дневной бюджет ящиков (≤10/сутки МСК) — сегодня в основную.
  Пары — один тип и область (однотипные первыми); получатели — объединение пулов, релевантные по Яндексу первыми.
  Письмо без меток: `Batch::$purpose='alias'` → блок `token` пуст, `{{tracking_token}}`/`{{request_number}}` из темы убраны
  (`CampaignEmailBuilder::cleanTaglessSubject`); внутренний реф батча в БД остаётся. Расписание — `AliasWindow::slots`
  (будни 9–18 МСК, шаг 300 с + до 90 с), диспетчер дополнительно не шлёт ящикам `pool=alias` вне окна; `recipientCaps`
  плана считает только письма к отправке сегодня. Тест: `emails:plan-day --alias-only --alias-items=ID --hold --force`,
  затем `alias-exp release {batch}`. Название организации в подписи — без хвоста ExportBase (`OrgReserve::displayName`).
- **Ф4** — `ReceiveAliasInboxJob` (очередь `receive`, из `emails:receive-dispatch`, независимо от флага ветки): IMAP `info@`
  домена; ящик-метка по `X-Original-To`, иначе To/Cc (`ParsedEmail::originalTo/recipients`); `IncomingEmailRouter::routeAlias`:
  письмо по `In-Reply-To`/`References` → `email_queue.email_service_response.message_id` (теперь пишется для всех писем,
  `QueuedEmailSender::rememberMessageId`), иначе по адресу отправителя, иначе по домену, иначе батч ящика + поставщик по адресу.
  Не адресованное ни одному ящику-метке (служебные Beget, письма на info@) — пропуск.
- Команда `alias-exp status | api-check {домен} | domain-add {домен} [--inbox=info] [--inbox-password=] | mailbox-create
  [--domain=] [--deadline=] | mailbox-drop {email} | release {batch} | drop-expired [--dry-run] | reserve-filter`; `drop-expired` — ежедневно 03:30 МСК.
- Конфиг `services.alias_exp` (`ALIAS_EXP_*`: доля 0.2, 2 позиции, пауза 300 с, лимит 120/сутки, ≤10 ящиков/сутки,
  окно 9–18 Europe/Moscow, срок +7 / 21 дн).

## Открыто
- Новые домены — владелец заведёт и пришлёт. Подключение: `alias-exp domain-add <домен>`.
- `setDomainMail` (катч-олл) через API ещё не проверен вживую — проверить на первом новом домене.
- Тестовый ящик `ivan.petrov@stroy-lift.ru` — оставлен (решение владельца).