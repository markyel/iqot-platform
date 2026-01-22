# API модерации таксономии IQOT

## Обзор

API для управления справочниками классификации товаров:
- **Домены** (`application_domains`) — области применения (Лифты, Эскалаторы, Станки...)
- **Типы товаров** (`product_types`) — иерархическая структура категорий

## Базовый URL

```
/api/admin/taxonomy
```

---

## 1. Домены (Application Domains)

### 1.1 Список доменов

```http
GET /api/admin/taxonomy/domains
```

**Query параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `status` | string | `all` / `active` / `inactive` / `pending` |
| `source` | string | `all` / `manual` / `ai_generated` |
| `search` | string | Поиск по имени/keywords |
| `sort` | string | `name` / `created_at` / `sort_order` / `items_count` |
| `order` | string | `asc` / `desc` |

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "slug": "elevators",
      "name": "Лифты",
      "description": "Лифтовое оборудование",
      "keywords": ["лифт", "elevator", "lift"],
      "parent_id": null,
      "is_active": true,
      "is_verified": true,
      "source": "manual",
      "sort_order": 1,
      "stats": {
        "items_count": 117,
        "suppliers_count": 45,
        "product_types_count": 28
      },
      "created_at": "2025-12-25T14:17:10Z",
      "updated_at": "2025-12-25T14:17:10Z"
    },
    {
      "id": 3,
      "slug": "cnc-machines",
      "name": "Станки ЧПУ",
      "description": "AI-сгенерированный домен для станочного оборудования",
      "keywords": ["станок", "чпу", "cnc", "machine"],
      "parent_id": null,
      "is_active": false,
      "is_verified": false,
      "source": "ai_generated",
      "sort_order": 99,
      "stats": {
        "items_count": 3,
        "suppliers_count": 0,
        "product_types_count": 5
      },
      "pending_review": {
        "created_by_workflow": "classify-new-domain",
        "trigger_item": "Шпиндель HSD ES915",
        "confidence": 0.87
      },
      "created_at": "2026-01-20T10:30:00Z"
    }
  ],
  "meta": {
    "total": 3,
    "pending_count": 1
  }
}
```

---

### 1.2 Получить домен

```http
GET /api/admin/taxonomy/domains/{id}
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "slug": "elevators",
    "name": "Лифты",
    "description": "Лифтовое оборудование: пассажирские, грузовые, коттеджные лифты",
    "keywords": ["лифт", "лифтовой", "elevator", "lift", "кабина", "шахта"],
    "parent_id": null,
    "is_active": true,
    "is_verified": true,
    "source": "manual",
    "sort_order": 1,
    "stats": {
      "items_count": 117,
      "suppliers_count": 45,
      "product_types_count": 28
    },
    "product_types": [
      {"id": 1, "name": "Приводы и частотники", "items_count": 23},
      {"id": 5, "name": "Платы и электроника", "items_count": 31}
    ],
    "recent_items": [
      {"id": 456, "name": "Частотник OVF20 KONE", "created_at": "2026-01-22T10:00:00Z"}
    ],
    "created_at": "2025-12-25T14:17:10Z",
    "updated_at": "2025-12-25T14:17:10Z"
  }
}
```

---

### 1.3 Создать домен

```http
POST /api/admin/taxonomy/domains
```

**Body:**
```json
{
  "name": "Медицинское оборудование",
  "slug": "medical-equipment",
  "description": "Запчасти для медицинского оборудования",
  "keywords": ["медицина", "medical", "рентген", "томограф"],
  "parent_id": null,
  "is_active": true,
  "sort_order": 10
}
```

**Ответ:** `201 Created`
```json
{
  "success": true,
  "data": {
    "id": 4,
    "slug": "medical-equipment",
    "name": "Медицинское оборудование",
    ...
  },
  "message": "Домен создан"
}
```

---

### 1.4 Обновить домен

```http
PUT /api/admin/taxonomy/domains/{id}
```

**Body:**
```json
{
  "name": "Станки с ЧПУ",
  "description": "Станочное оборудование с числовым программным управлением",
  "keywords": ["станок", "чпу", "cnc", "токарный", "фрезерный"],
  "is_active": true,
  "is_verified": true,
  "sort_order": 3
}
```

---

### 1.5 Удалить домен

```http
DELETE /api/admin/taxonomy/domains/{id}
```

**Query параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `mode` | string | `soft` (default) / `hard` |
| `migrate_to` | int | ID домена для переноса связанных данных |

**Примеры:**

```http
# Мягкое удаление (is_active = false)
DELETE /api/admin/taxonomy/domains/3?mode=soft

# Жёсткое удаление с переносом данных
DELETE /api/admin/taxonomy/domains/3?mode=hard&migrate_to=1
```

---

### 1.6 Одобрить AI-домен

```http
POST /api/admin/taxonomy/domains/{id}/approve
```

**Body (опционально):**
```json
{
  "name": "Станки ЧПУ",
  "keywords": ["станок", "чпу", "cnc"],
  "sort_order": 3
}
```

**Действие:**
- `is_active = true`
- `is_verified = true`
- `source` остаётся `ai_generated`
- Применяются переданные корректировки

---

### 1.7 Отклонить AI-домен

```http
POST /api/admin/taxonomy/domains/{id}/reject
```

**Body:**
```json
{
  "reason": "Дубликат существующего домена",
  "merge_into": 1,
  "reassign_items": true
}
```

**Действия:**
- Если `merge_into` указан — переносит items и types в указанный домен
- Удаляет отклонённый домен

---

### 1.8 Объединить домены

```http
POST /api/admin/taxonomy/domains/merge
```

**Body:**
```json
{
  "source_ids": [3, 4],
  "target_id": 1,
  "merge_keywords": true,
  "delete_sources": true
}
```

**Действия:**
- Переносит все `request_items` в target
- Переносит все `product_types` в target
- Переносит `supplier_domains` в target
- Объединяет keywords (если `merge_keywords: true`)
- Удаляет source домены (если `delete_sources: true`)

---

## 2. Типы товаров (Product Types)

### 2.1 Дерево типов

```http
GET /api/admin/taxonomy/product-types/tree
```

**Query параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `domain_id` | int | Фильтр по домену |
| `status` | string | `all` / `active` / `inactive` / `pending` |
| `depth` | int | Глубина дерева (default: все) |

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "slug": "drives-inverters",
      "name": "Приводы и частотники",
      "is_leaf": false,
      "is_active": true,
      "is_verified": true,
      "source": "manual",
      "items_count": 0,
      "children": [
        {
          "id": 2,
          "slug": "frequency-inverters",
          "name": "Частотные преобразователи",
          "is_leaf": true,
          "is_active": true,
          "is_verified": true,
          "keywords": ["частотник", "инвертор", "vfd", "ovf"],
          "items_count": 23,
          "children": []
        },
        {
          "id": 3,
          "slug": "servos",
          "name": "Сервоприводы",
          "is_leaf": true,
          "is_active": true,
          "keywords": ["сервопривод", "серво", "servo"],
          "items_count": 8,
          "children": []
        }
      ]
    },
    {
      "id": 10,
      "slug": "spindles",
      "name": "Шпиндели",
      "is_leaf": true,
      "is_active": false,
      "is_verified": false,
      "source": "ai_generated",
      "keywords": ["шпиндель", "spindle", "hsd"],
      "items_count": 3,
      "pending_review": {
        "created_by_workflow": "classify-new-type",
        "trigger_item": "Шпиндель HSD ES915",
        "suggested_parent": null,
        "suggested_domain": "cnc-machines"
      },
      "children": []
    }
  ],
  "meta": {
    "total": 45,
    "leaf_count": 38,
    "pending_count": 2
  }
}
```

---

### 2.2 Плоский список типов

```http
GET /api/admin/taxonomy/product-types
```

**Query параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `is_leaf` | bool | Только листовые / только группы |
| `parent_id` | int | Дочерние элементы родителя |
| `domain_id` | int | Типы связанные с доменом |
| `status` | string | `all` / `active` / `inactive` / `pending` |
| `source` | string | `all` / `manual` / `ai_generated` |
| `search` | string | Поиск по имени/keywords |
| `sort` | string | `name` / `created_at` / `items_count` |

---

### 2.3 Получить тип

```http
GET /api/admin/taxonomy/product-types/{id}
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "slug": "frequency-inverters",
    "name": "Частотные преобразователи",
    "description": "Частотные преобразователи (инверторы) для лифтов",
    "keywords": ["частотник", "инвертор", "vfd", "ovf20", "a1000"],
    "parent_id": 1,
    "parent": {
      "id": 1,
      "name": "Приводы и частотники"
    },
    "is_leaf": true,
    "is_active": true,
    "is_verified": true,
    "source": "manual",
    "sort_order": 1,
    "domains": [
      {"id": 1, "name": "Лифты", "is_specific": true},
      {"id": 2, "name": "Эскалаторы", "is_specific": false}
    ],
    "stats": {
      "items_count": 23,
      "suppliers_count": 12,
      "avg_confidence": 0.89
    },
    "recent_items": [
      {"id": 456, "name": "Частотник OVF20 KONE", "confidence": 0.95}
    ],
    "breadcrumb": [
      {"id": 1, "name": "Приводы и частотники"},
      {"id": 2, "name": "Частотные преобразователи"}
    ]
  }
}
```

---

### 2.4 Создать тип

```http
POST /api/admin/taxonomy/product-types
```

**Body:**
```json
{
  "name": "Электромагнитные тормоза",
  "slug": "electromagnetic-brakes",
  "description": "Тормоза для лифтовых лебёдок",
  "keywords": ["тормоз", "brake", "электромагнитный", "bst"],
  "parent_id": 1,
  "is_leaf": true,
  "is_active": true,
  "sort_order": 5,
  "domain_ids": [1, 2]
}
```

---

### 2.5 Обновить тип

```http
PUT /api/admin/taxonomy/product-types/{id}
```

**Body:**
```json
{
  "name": "Шпиндели станочные",
  "keywords": ["шпиндель", "spindle", "hsd", "es915", "шпиндельный узел"],
  "parent_id": 15,
  "is_active": true,
  "is_verified": true,
  "domain_ids": [3]
}
```

---

### 2.6 Переместить тип

```http
POST /api/admin/taxonomy/product-types/{id}/move
```

**Body:**
```json
{
  "new_parent_id": 5,
  "position": "after",
  "reference_id": 12
}
```

| Параметр | Описание |
|----------|----------|
| `new_parent_id` | Новый родитель (`null` = корень) |
| `position` | `first` / `last` / `before` / `after` |
| `reference_id` | ID соседа для `before`/`after` |

---

### 2.7 Удалить тип

```http
DELETE /api/admin/taxonomy/product-types/{id}
```

**Query параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `mode` | string | `soft` / `hard` |
| `migrate_to` | int | ID типа для переноса items |
| `children_action` | string | `move_up` / `delete` / `migrate` |

**Примеры:**

```http
# Мягкое удаление
DELETE /api/admin/taxonomy/product-types/10?mode=soft

# Удаление с переносом items и подъёмом children
DELETE /api/admin/taxonomy/product-types/10?mode=hard&migrate_to=2&children_action=move_up
```

---

### 2.8 Одобрить AI-тип

```http
POST /api/admin/taxonomy/product-types/{id}/approve
```

**Body (опционально):**
```json
{
  "name": "Шпиндели",
  "parent_id": 15,
  "keywords": ["шпиндель", "spindle"],
  "domain_ids": [3]
}
```

---

### 2.9 Отклонить AI-тип

```http
POST /api/admin/taxonomy/product-types/{id}/reject
```

**Body:**
```json
{
  "reason": "Слишком узкая категория",
  "merge_into": 5,
  "reassign_items": true
}
```

---

### 2.10 Объединить типы

```http
POST /api/admin/taxonomy/product-types/merge
```

**Body:**
```json
{
  "source_ids": [10, 11],
  "target_id": 5,
  "merge_keywords": true,
  "delete_sources": true
}
```

---

### 2.11 Конвертировать группу ↔ лист

```http
POST /api/admin/taxonomy/product-types/{id}/convert
```

**Body:**
```json
{
  "to": "leaf"
}
```

| `to` | Условие | Действие |
|------|---------|----------|
| `leaf` | Нет дочерних | `is_leaf = true` |
| `group` | — | `is_leaf = false`, items переносятся |

---

## 3. Связи домен ↔ тип

### 3.1 Список связей

```http
GET /api/admin/taxonomy/domain-types
```

**Query:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `domain_id` | int | Фильтр по домену |
| `product_type_id` | int | Фильтр по типу |

---

### 3.2 Создать связь

```http
POST /api/admin/taxonomy/domain-types
```

**Body:**
```json
{
  "domain_id": 1,
  "product_type_id": 10,
  "is_specific": true
}
```

---

### 3.3 Удалить связь

```http
DELETE /api/admin/taxonomy/domain-types/{domain_id}/{product_type_id}
```

---

### 3.4 Массовое обновление связей типа

```http
PUT /api/admin/taxonomy/product-types/{id}/domains
```

**Body:**
```json
{
  "domain_ids": [1, 2, 3],
  "is_specific": true
}
```

---

## 4. Очередь модерации

### 4.1 Список ожидающих модерации

```http
GET /api/admin/taxonomy/pending
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "domains": [
      {
        "id": 3,
        "name": "Станки ЧПУ",
        "source": "ai_generated",
        "confidence": 0.87,
        "trigger_item": "Шпиндель HSD ES915",
        "created_at": "2026-01-20T10:30:00Z",
        "items_count": 3
      }
    ],
    "product_types": [
      {
        "id": 10,
        "name": "Шпиндели",
        "parent_name": null,
        "suggested_parent": "Механика",
        "source": "ai_generated",
        "confidence": 0.82,
        "trigger_item": "Шпиндель HSD ES915",
        "created_at": "2026-01-20T10:30:00Z",
        "items_count": 3
      }
    ]
  },
  "meta": {
    "total_pending": 2,
    "domains_pending": 1,
    "types_pending": 1
  }
}
```

---

### 4.2 Массовое одобрение

```http
POST /api/admin/taxonomy/pending/bulk-approve
```

**Body:**
```json
{
  "domains": [3, 4],
  "product_types": [10, 11, 12]
}
```

---

### 4.3 Массовое отклонение

```http
POST /api/admin/taxonomy/pending/bulk-reject
```

**Body:**
```json
{
  "domains": [
    {"id": 5, "merge_into": 1}
  ],
  "product_types": [
    {"id": 13, "merge_into": 2}
  ]
}
```

---

## 5. Утилиты

### 5.1 Проверить slug

```http
GET /api/admin/taxonomy/check-slug
```

**Query:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `slug` | string | Проверяемый slug |
| `type` | string | `domain` / `product_type` |
| `exclude_id` | int | Исключить из проверки |

**Ответ:**
```json
{
  "available": false,
  "suggestion": "cnc-machines-2"
}
```

---

### 5.2 Сгенерировать slug

```http
POST /api/admin/taxonomy/generate-slug
```

**Body:**
```json
{
  "name": "Станки с ЧПУ",
  "type": "domain"
}
```

**Ответ:**
```json
{
  "slug": "stanki-s-chpu"
}
```

---

### 5.3 Статистика таксономии

```http
GET /api/admin/taxonomy/stats
```

**Ответ:**
```json
{
  "domains": {
    "total": 3,
    "active": 2,
    "pending": 1,
    "ai_generated": 1
  },
  "product_types": {
    "total": 45,
    "active": 43,
    "pending": 2,
    "leaf_count": 38,
    "group_count": 7,
    "max_depth": 2,
    "ai_generated": 5
  },
  "coverage": {
    "items_classified": 124,
    "items_unclassified": 3,
    "classification_rate": 0.976
  }
}
```

---

### 5.4 Экспорт таксономии

```http
GET /api/admin/taxonomy/export
```

**Query:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `format` | string | `json` / `csv` / `yaml` |
| `include` | string | `all` / `domains` / `types` / `active_only` |

---

### 5.5 Импорт таксономии

```http
POST /api/admin/taxonomy/import
```

**Body:**
```json
{
  "format": "json",
  "data": {...},
  "mode": "merge",
  "dry_run": true
}
```

| Параметр | Описание |
|----------|----------|
| `mode` | `merge` (добавить новое) / `replace` (перезаписать) |
| `dry_run` | Только проверить, не применять |

---

## 6. Коды ошибок

| Код | Описание |
|-----|----------|
| 400 | Некорректные данные |
| 404 | Сущность не найдена |
| 409 | Конфликт (дубликат slug, циклическая ссылка) |
| 422 | Нарушение бизнес-правил |

**Пример ошибки:**
```json
{
  "success": false,
  "error": {
    "code": "CANNOT_DELETE_WITH_CHILDREN",
    "message": "Невозможно удалить тип с дочерними элементами",
    "details": {
      "children_count": 3,
      "suggestion": "Используйте children_action=move_up или children_action=delete"
    }
  }
}
```

---

## 7. Webhooks (опционально)

При изменениях в таксономии можно отправлять webhooks:

```json
{
  "event": "taxonomy.domain.approved",
  "data": {
    "id": 3,
    "name": "Станки ЧПУ",
    "approved_by": "admin@example.com"
  },
  "timestamp": "2026-01-22T15:30:00Z"
}
```

Типы событий:
- `taxonomy.domain.created`
- `taxonomy.domain.approved`
- `taxonomy.domain.rejected`
- `taxonomy.domain.merged`
- `taxonomy.product_type.created`
- `taxonomy.product_type.approved`
- `taxonomy.product_type.rejected`
- `taxonomy.product_type.moved`
- `taxonomy.product_type.merged`
