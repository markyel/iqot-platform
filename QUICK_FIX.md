# 🚨 БЫСТРОЕ ИСПРАВЛЕНИЕ

## Проблема
TypeError: Cannot assign null to property

## Решение

### Добавьте в .env:

```env
# n8n настройки
N8N_WEBHOOK_URL=https://liftway.app.n8n.cloud/webhook
N8N_AUTH_TOKEN=ваш_основной_токен
N8N_PARSE_AUTH_TOKEN=iqot_parse_api_2024_secret

# Для обратной совместимости со старым кодом
N8N_PARSE_WEBHOOK_URL=https://liftway.app.n8n.cloud/webhook/parse-request
```

### Очистите кэш:

```bash
php artisan config:clear
php artisan cache:clear
```

### Обновите браузер:

Ctrl+F5

---

## Теперь должно работать:

✅ `/cabinet/my/requests/create` - создание заявок пользователями
✅ `/manage/manage-requests/create` - создание заявок админом

---

## Если всё равно не работает

Запустите полную диагностику:

```bash
php check-setup.php
```
