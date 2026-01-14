@extends('layouts.cabinet')

@section('title', 'Редактировать заявку ' . ($request['request_number'] ?? ''))
@section('header', 'Редактировать заявку ' . ($request['request_number'] ?? ''))

@push('styles')
<style>
    .form-container { max-width: 1200px; margin: 0 auto; }
    .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
    .card-header { padding: 1.25rem; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #111827; display: flex; justify-content: space-between; align-items: center; }
    .card-body { padding: 1.5rem; }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem; }
    .form-group label.required::after { content: ' *'; color: #ef4444; }
    .form-control { width: 100%; padding: 0.625rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; }
    .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .btn { padding: 0.625rem 1.25rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; font-size: 0.875rem; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .btn-secondary { background: #6b7280; color: white; }
    .btn-secondary:hover { background: #4b5563; }
    .btn-success { background: #10b981; color: white; }
    .btn-success:hover { background: #059669; }
    .btn-outline-danger { background: transparent; border: 1px solid #ef4444; color: #ef4444; }
    .btn-outline-danger:hover { background: #ef4444; color: white; }
    .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }
    .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
    .items-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .items-table th, .items-table td { padding: 0.75rem; border: 1px solid #e5e7eb; font-size: 0.875rem; }
    .items-table th { background: #f9fafb; font-weight: 600; color: #6b7280; text-align: left; }
    .items-table input, .items-table select, .items-table textarea { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; }
    .items-table textarea { min-height: 60px; resize: vertical; }
    .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; }
    .hidden { display: none; }
    .two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
</style>
@endpush

@section('content')
<div class="form-container">

    @if($errors->any())
    <div class="alert" style="background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444;">
        <strong>Ошибки валидации:</strong>
        <ul style="margin: 0.5rem 0 0 1.5rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(session('error'))
    <div class="alert" style="background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444;">
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
                <div class="two-cols">
                    <div class="form-group">
                        <label for="status" class="required">Статус</label>
                        <select name="status" id="status" class="form-control" required>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ (old('status', $request['status']) === $value) ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="is_customer_request">
                            <input type="checkbox" name="is_customer_request" id="is_customer_request" value="1"
                                {{ old('is_customer_request', $request['is_customer_request'] ?? false) ? 'checked' : '' }}>
                            Именная заявка
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Данные клиента -->
        <div class="card {{ (old('is_customer_request', $request['is_customer_request'] ?? false)) ? '' : 'hidden' }}" id="customer-fields">
            <div class="card-header">Данные клиента</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="client_organization_id">Организация</label>
                    <select name="client_organization_id" id="client_organization_id" class="form-control">
                        <option value="">-- Не выбрано --</option>
                        @foreach($organizations as $id => $name)
                            <option value="{{ $id }}" {{ old('client_organization_id', $request['client_organization_id'] ?? null) == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="two-cols">
                    <div class="form-group">
                        <label for="customer_company" class="required">Название компании</label>
                        <input type="text" name="customer_company" id="customer_company" class="form-control"
                            value="{{ old('customer_company', $request['customer_company'] ?? '') }}">
                    </div>

                    <div class="form-group">
                        <label for="customer_contact_person">Контактное лицо</label>
                        <input type="text" name="customer_contact_person" id="customer_contact_person" class="form-control"
                            value="{{ old('customer_contact_person', $request['customer_contact_person'] ?? '') }}">
                    </div>
                </div>

                <div class="two-cols">
                    <div class="form-group">
                        <label for="customer_email">Email</label>
                        <input type="email" name="customer_email" id="customer_email" class="form-control"
                            value="{{ old('customer_email', $request['customer_email'] ?? '') }}">
                    </div>

                    <div class="form-group">
                        <label for="customer_phone">Телефон</label>
                        <input type="text" name="customer_phone" id="customer_phone" class="form-control"
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
                <table class="items-table">
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
                    <label for="notes">Заметки к заявке</label>
                    <textarea name="notes" id="notes" class="form-control" rows="4">{{ old('notes', $request['notes'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Кнопки действий -->
        <div class="form-actions">
            <a href="{{ route('admin.manage.requests.show', $request['id']) }}" class="btn btn-secondary">Отмена</a>
            <button type="submit" class="btn btn-success">Сохранить изменения</button>
        </div>
    </form>

</div>

@push('scripts')
<script>
// Показать/скрыть поля клиента
document.getElementById('is_customer_request').addEventListener('change', function() {
    const customerFields = document.getElementById('customer-fields');
    const customerCompany = document.getElementById('customer_company');

    if (this.checked) {
        customerFields.classList.remove('hidden');
        customerCompany.required = true;
    } else {
        customerFields.classList.add('hidden');
        customerCompany.required = false;
    }
});
</script>
@endpush
@endsection
