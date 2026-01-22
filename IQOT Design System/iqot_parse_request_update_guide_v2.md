# Обновление IQOT Web Parse Request
## Автоматическое создание новых типов и доменов (Batch версия)

---

## Обзор

**Новое поведение:**
- AI пытается найти подходящий тип/домен из существующих
- Если НЕ находит — предлагает создать новый (`create_new`)
- Новые типы/домены создаются со статусом `pending`
- Заявки работают нормально, модератор проверяет позже

---

## 1. Миграции БД

```sql
-- Добавляем поля status и created_by в product_types
ALTER TABLE product_types 
ADD COLUMN status ENUM('active', 'pending') DEFAULT 'active' AFTER is_active,
ADD COLUMN created_by ENUM('manual', 'ai_suggested') DEFAULT 'manual' AFTER status;

-- Добавляем поля status и created_by в application_domains
ALTER TABLE application_domains 
ADD COLUMN status ENUM('active', 'pending') DEFAULT 'active' AFTER is_active,
ADD COLUMN created_by ENUM('manual', 'ai_suggested') DEFAULT 'manual' AFTER status;

-- Индексы
CREATE INDEX idx_product_types_status ON product_types(status);
CREATE INDEX idx_domains_status ON application_domains(status);

-- Обновляем существующие записи
UPDATE product_types SET status = 'active', created_by = 'manual' WHERE status IS NULL;
UPDATE application_domains SET status = 'active', created_by = 'manual' WHERE status IS NULL;
```

---

## 2. Новая структура воркфлоу

```
Webhook
    │
    ├──► Load Categories
    ├──► Load Migration Flags  
    ├──► Load Product Types
    └──► Load Domains
            │
            ▼
        Merge Refs 1 → Merge Refs 2 → Merge Refs 3
            │
            ▼
        Merge Data (ОБНОВЛЁН)
            │
            ▼
        AI Agent
            │
            ▼
        Parse AI Response (ОБНОВЛЁН)
            │
            ▼
        Prepare Types Insert (НОВАЯ)
            │
            ├─── skip_types=true ───► Prepare Domains Insert
            │                                │
            └─── skip_types=false ──► Batch Create Types
                                            │
                                            ▼
                                    Prepare Domains Insert (НОВАЯ)
                                            │
                                ├─── skip_domains=true ───► Assign New IDs
                                │                                │
                                └─── skip_domains=false ──► Batch Create Domains
                                                                │
                                                                ▼
                                                        Assign New IDs (НОВАЯ)
                                                                │
                                                                ▼
                                                            Respond
```

---

## 3. Обновление существующих нод

### 3.1 Load Product Types

Добавить `status` в SELECT:

```sql
SELECT 
  pt.id, 
  pt.slug, 
  pt.name, 
  pt.parent_id, 
  pt.keywords, 
  pt.is_leaf,
  pt.status,
  parent.name AS parent_name 
FROM product_types pt 
LEFT JOIN product_types parent ON parent.id = pt.parent_id 
WHERE pt.is_active = TRUE 
ORDER BY pt.sort_order, pt.name
```

### 3.2 Load Domains

Добавить `status` в SELECT:

```sql
SELECT id, slug, name, keywords, status
FROM application_domains 
WHERE is_active = TRUE 
ORDER BY sort_order
```

### 3.3 Merge Data

**Заменить код на:** `merge_data_updated.js`

### 3.4 Parse AI Response

**Заменить код на:** `parse_ai_response_updated.js`

---

## 4. Новые ноды

### 4.1 Prepare Types Insert (Code)

**Позиция:** после Parse AI Response

```javascript
// ============================================
// PREPARE TYPES INSERT (Code Node)
// ============================================

const data = $input.first().json;
const types = data.types_to_create || [];

if (types.length === 0) {
  return { 
    json: { 
      ...data,
      skip_types: true,
      types_values: '',
      types_names: '',
      types_temp_map: []
    } 
  };
}

const values = types.map(t => {
  const name = t.name.replace(/'/g, "''");
  const slug = t.slug;
  const parentId = t.parent_id || 'NULL';
  return `('${name}', '${slug}', ${parentId}, 1, 1, 'pending', 'ai_suggested', 999)`;
}).join(',\n');

const names = types.map(t => `'${t.name.replace(/'/g, "''")}'`).join(', ');

const tempMap = types.map(t => ({
  name: t.name,
  temp_id: t.temp_id
}));

return {
  json: {
    ...data,
    skip_types: false,
    types_values: values,
    types_names: names,
    types_temp_map: tempMap
  }
};
```

---

### 4.2 IF: Has New Types?

**Условие:**
```
{{ $json.skip_types === false }}
```

**True →** Batch Create Types
**False →** Prepare Domains Insert

---

### 4.3 Batch Create Types (MySQL)

```sql
INSERT IGNORE INTO product_types (
  name, slug, parent_id, is_leaf, is_active, status, created_by, sort_order
)
VALUES 
{{ $json.types_values }};

SELECT id, name FROM product_types 
WHERE name IN ({{ $json.types_names }});
```

**Credentials:** MySQL account

---

### 4.4 Merge After Types (Merge)

**Mode:** Combine → By Position

Объединяет результаты из:
- Input 1: IF False (skip_types=true)
- Input 2: Batch Create Types (skip_types=false)

---

### 4.5 Prepare Domains Insert (Code)

```javascript
// ============================================
// PREPARE DOMAINS INSERT (Code Node)
// ============================================

const data = $input.first().json;
const domains = data.domains_to_create || [];

if (domains.length === 0) {
  return { 
    json: { 
      ...data,
      skip_domains: true,
      domains_values: '',
      domains_names: '',
      domains_temp_map: []
    } 
  };
}

const values = domains.map(d => {
  const name = d.name.replace(/'/g, "''");
  const slug = d.slug;
  return `('${name}', '${slug}', NULL, 1, 'pending', 'ai_suggested', 999)`;
}).join(',\n');

const names = domains.map(d => `'${d.name.replace(/'/g, "''")}'`).join(', ');

const tempMap = domains.map(d => ({
  name: d.name,
  temp_id: d.temp_id
}));

return {
  json: {
    ...data,
    skip_domains: false,
    domains_values: values,
    domains_names: names,
    domains_temp_map: tempMap
  }
};
```

---

### 4.6 IF: Has New Domains?

**Условие:**
```
{{ $json.skip_domains === false }}
```

**True →** Batch Create Domains
**False →** Assign New IDs

---

### 4.7 Batch Create Domains (MySQL)

```sql
INSERT IGNORE INTO application_domains (
  name, slug, parent_id, is_active, status, created_by, sort_order
)
VALUES 
{{ $json.domains_values }};

SELECT id, name FROM application_domains 
WHERE name IN ({{ $json.domains_names }});
```

---

### 4.8 Merge After Domains (Merge)

**Mode:** Combine → By Position

Объединяет результаты из:
- Input 1: IF False (skip_domains=true)
- Input 2: Batch Create Domains (skip_domains=false)

---

### 4.9 Assign New IDs (Code)

```javascript
// ============================================
// ASSIGN NEW IDS (Code Node) - BATCH VERSION
// ============================================

const inputData = $input.first().json;

const items = inputData.items || [];
const typesTempMap = inputData.types_temp_map || [];
const domainsTempMap = inputData.domains_temp_map || [];

const typeIdMap = {};
const domainIdMap = {};

// Результаты создания типов
try {
  const createdTypes = $('Batch Create Types').all();
  createdTypes.forEach(row => {
    if (row.json.id && row.json.name) {
      const mapping = typesTempMap.find(m => m.name === row.json.name);
      if (mapping) {
        typeIdMap[mapping.temp_id] = row.json.id;
      }
    }
  });
} catch (e) {}

// Результаты создания доменов
try {
  const createdDomains = $('Batch Create Domains').all();
  createdDomains.forEach(row => {
    if (row.json.id && row.json.name) {
      const mapping = domainsTempMap.find(m => m.name === row.json.name);
      if (mapping) {
        domainIdMap[mapping.temp_id] = row.json.id;
      }
    }
  });
} catch (e) {}

// Обновляем items
const updatedItems = items.map(item => {
  const updated = { ...item };
  
  if (typeof updated.product_type_id === 'string' && updated.product_type_id.startsWith('temp_type_')) {
    updated.product_type_id = typeIdMap[updated.product_type_id] || null;
  }
  
  if (typeof updated.domain_id === 'string' && updated.domain_id.startsWith('temp_domain_')) {
    updated.domain_id = domainIdMap[updated.domain_id] || null;
  }
  
  return updated;
});

return {
  json: {
    success: inputData.success,
    is_purchase_request: inputData.is_purchase_request,
    confidence: inputData.confidence,
    is_valid: inputData.is_valid,
    items: updatedItems,
    items_count: inputData.items_count,
    missing_info: inputData.missing_info || [],
    use_new_classification: inputData.use_new_classification,
    created_types: Object.keys(typeIdMap).length,
    created_domains: Object.keys(domainIdMap).length,
    has_new_classifications: inputData.has_new_classifications
  }
};
```

---

### 4.10 Respond

**Обновить responseBody:**

```
={{ JSON.stringify($json) }}
```

---

## 5. Connections (связи между нодами)

```
Parse AI Response → Prepare Types Insert

Prepare Types Insert → IF: Has New Types?

IF: Has New Types? (true) → Batch Create Types
IF: Has New Types? (false) → Prepare Domains Insert

Batch Create Types → Prepare Domains Insert

Prepare Domains Insert → IF: Has New Domains?

IF: Has New Domains? (true) → Batch Create Domains
IF: Has New Domains? (false) → Assign New IDs

Batch Create Domains → Assign New IDs

Assign New IDs → Respond
```

---

## 6. Тестирование

### Тест 1: Всё существующее
```
Input: "Плата KONE LCE FCB 2шт"
Expected: product_type_id = 101, domain_id = 1
DB: без изменений
```

### Тест 2: Новый тип
```
Input: "Диспетчерский модуль KONE 1шт"
Expected: product_type_id = (новый), domain_id = 1
DB: INSERT INTO product_types (name='Диспетчеризация', status='pending')
```

### Тест 3: Новый тип И домен
```
Input: "Шпиндель для станка Haas VF-2"
Expected: product_type_id = (новый), domain_id = (новый)
DB: 
  - INSERT INTO product_types (name='Шпиндели', status='pending')
  - INSERT INTO application_domains (name='Станки ЧПУ', status='pending')
```

---

## 7. Проверка в БД

```sql
-- Pending типы
SELECT * FROM product_types WHERE status = 'pending';

-- Pending домены
SELECT * FROM application_domains WHERE status = 'pending';
```

---

## 8. TODO: Laravel API для модерации

```
GET  /api/classifications/pending
POST /api/product-types/{id}/approve
PATCH /api/product-types/{id}
DELETE /api/product-types/{id}
POST /api/product-types/{id}/merge/{target_id}
```
