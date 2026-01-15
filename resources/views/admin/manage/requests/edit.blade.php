@extends('layouts.cabinet')

@section('title', 'Редактировать заявку ' . ($request['request_number'] ?? ''))

@section('content')
<x-page-header
    title="Редактировать заявку {{ $request['request_number'] ?? '' }}"
    description="Измените настройки заявки и информацию о клиенте"
/>
<div style="max-width: 1200px; margin: 0 auto;">

    @if($errors->any())
    <div class="alert alert-danger">
        <strong>Ошибки валидации:</strong>
        <ul style="margin: var(--space-2) 0 0 var(--space-6);">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger">
        <strong>{{ session('error') }}</strong>
    </div>
    @endif

    <form action="{{ route('admin.manage.requests.update', $request['id']) }}" method="POST" id="request-form">
        @csrf
        @method('PUT')

        <!-- Основные настройки -->
        <div class="card">
            <div class="card-header">Основные настройки</div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
                    <div class="form-group">
                        <label for="status" class="form-label required">Статус</label>
                        <select name="status" id="status" class="select" required>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ (old('status', $request['status']) === $value) ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                            <input type="checkbox" name="is_customer_request" id="is_customer_request" value="1"
                                {{ old('is_customer_request', $request['is_customer_request'] ?? false) ? 'checked' : '' }} style="width: 1.25rem; height: 1.25rem;">
                            <span class="form-label" style="margin: 0;">Именная заявка</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Данные клиента -->
        <div class="card" style="{{ (old('is_customer_request', $request['is_customer_request'] ?? false)) ? '' : 'display: none;' }}" id="customer-fields">
            <div class="card-header">Данные клиента</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="client_organization_id" class="form-label">Организация</label>
                    <select name="client_organization_id" id="client_organization_id" class="select">
                        <option value="">-- Не выбрано --</option>
                        @foreach($organizations as $id => $name)
                            <option value="{{ $id }}" {{ old('client_organization_id', $request['client_organization_id'] ?? null) == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
                    <div class="form-group">
                        <label for="customer_company" class="form-label required">Название компании</label>
                        <input type="text" name="customer_company" id="customer_company" class="input"
                            value="{{ old('customer_company', $request['customer_company'] ?? '') }}">
                    </div>

                    <div class="form-group">
                        <label for="customer_contact_person" class="form-label">Контактное лицо</label>
                        <input type="text" name="customer_contact_person" id="customer_contact_person" class="input"
                            value="{{ old('customer_contact_person', $request['customer_contact_person'] ?? '') }}">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
                    <div class="form-group">
                        <label for="customer_email" class="form-label">Email</label>
                        <input type="email" name="customer_email" id="customer_email" class="input"
                            value="{{ old('customer_email', $request['customer_email'] ?? '') }}">
                    </div>

                    <div class="form-group">
                        <label for="customer_phone" class="form-label">Телефон</label>
                        <input type="text" name="customer_phone" id="customer_phone" class="input"
                            value="{{ old('customer_phone', $request['customer_phone'] ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        <!-- Позиции заявки (только для просмотра, редактирование не поддерживается API) -->
        <div class="card">
            <div class="card-header">
                <span>Позиции заявки (только для просмотра)</span>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 30px;">#</th>
                            <th style="width: 250px;">Название *</th>
                            <th style="width: 100px;">Бренд</th>
                            <th style="width: 100px;">Артикул</th>
                            <th style="width: 80px;">Кол-во *</th>
                            <th style="width: 80px;">Ед. изм. *</th>
                            <th style="width: 120px;">Категория *</th>
                            <th style="width: 120px;">Тип товара</th>
                            <th style="width: 120px;">Область</th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                        @php
                            $items = $request['items'] ?? [];
                        @endphp
                        @if(!empty($items))
                            @foreach($items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item['name'] ?? '' }}</td>
                                    <td>{{ $item['brand'] ?? '-' }}</td>
                                    <td>{{ $item['article'] ?? '-' }}</td>
                                    <td>{{ $item['quantity'] ?? 1 }}</td>
                                    <td>{{ $item['unit'] ?? 'шт' }}</td>
                                    <td>{{ $item['category'] ?? '-' }}</td>
                                    <td>
                                        @if(!empty($item['product_type_id']))
                                            {{ $productTypes[$item['product_type_id']] ?? $item['product_type_id'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($item['domain_id']))
                                            {{ $applicationDomains[$item['domain_id']] ?? $item['domain_id'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Дополнительно -->
        <div class="card">
            <div class="card-header">Дополнительно</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="notes" class="form-label">Заметки к заявке</label>
                    <textarea name="notes" id="notes" class="input" rows="4">{{ old('notes', $request['notes'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Кнопки действий -->
        <div style="display: flex; gap: var(--space-4); justify-content: flex-end; margin-top: var(--space-8);">
            <x-button tag="a" href="{{ route('admin.manage.requests.show', $request['id']) }}" variant="secondary">
                Отмена
            </x-button>
            <x-button type="submit" variant="success">
                <i data-lucide="check" style="width: 1rem; height: 1rem;"></i>
                Сохранить изменения
            </x-button>
        </div>
    </form>

</div>

@push('scripts')
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}

// Показать/скрыть поля клиента
document.getElementById('is_customer_request').addEventListener('change', function() {
    const customerFields = document.getElementById('customer-fields');
    const customerCompany = document.getElementById('customer_company');

    if (this.checked) {
        customerFields.style.display = 'block';
        customerCompany.required = true;
    } else {
        customerFields.style.display = 'none';
        customerCompany.required = false;
    }
});
</script>
@endpush
@endsection
