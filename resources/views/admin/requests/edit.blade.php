@extends('layouts.cabinet')

@section('title', 'Редактирование заявки ' . ($request->request_number ?? $request->code))

@section('content')
@php
    $categories = \App\Models\Category::getActiveForSelect();
    $productTypes = \App\Models\ProductType::getActiveForSelect();
    $applicationDomains = \App\Models\ApplicationDomain::getActiveForSelect();
@endphp

<x-page-header
    :title="'Редактирование заявки ' . ($request->request_number ?? $request->code)"
    :breadcrumbs="[
        ['label' => 'Заявки', 'url' => route('admin.requests.index')],
        ['label' => $request->request_number ?? $request->code, 'url' => route('admin.requests.show', $request->id)],
        ['label' => 'Редактировать']
    ]"
>
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.requests.show', $request->id)" icon="arrow-left">
            Назад к заявке
        </x-button>
    </x-slot>
</x-page-header>

@if(session('error'))
<div class="alert alert-error">
    <i data-lucide="x-circle" class="alert-icon"></i>
    <div class="alert-content">
        {{ session('error') }}
    </div>
</div>
@endif

@if($errors->any())
<div class="alert alert-error">
    <i data-lucide="x-circle" class="alert-icon"></i>
    <div class="alert-content">
        <strong>Ошибки валидации:</strong>
        <ul style="margin: var(--space-2) 0 0 var(--space-6); padding-left: 0; list-style-position: inside;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<form method="POST" action="{{ route('admin.requests.update', $request->id) }}">
    @csrf
    @method('PUT')

    <!-- Основная информация -->
    <div class="card">
        <div class="card-header">Основная информация</div>
        <div class="card-body">
            <div class="form-group">
                <label for="title" class="form-label form-label-required">Заголовок заявки</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    class="input @error('title') input-error @enderror"
                    value="{{ old('title', $request->title) }}"
                    required
                >
                @error('title')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Примечания</label>
                <textarea
                    id="notes"
                    name="notes"
                    class="input textarea @error('notes') input-error @enderror"
                    rows="4"
                >{{ old('notes', $request->notes) }}</textarea>
                @error('notes')
                    <span class="form-error">{{ $message }}</span>
                @enderror
                <span class="form-hint">Внутренние комментарии, видны только сотрудникам</span>
            </div>
        </div>
    </div>

    <!-- Позиции заявки -->
    <div class="card">
        <div class="card-header">Позиции заявки</div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Название *</th>
                            <th>Бренд</th>
                            <th>Артикул</th>
                            <th style="width: 100px;">Кол-во *</th>
                            <th style="width: 100px;">Ед. изм. *</th>
                            <th>Категория *</th>
                            <th>Тип оборудования</th>
                            <th>Область применения</th>
                            <th style="width: 200px;">Описание</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($request->items as $index => $item)
                        <tr>
                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">

                            <td data-label="#">{{ $index + 1 }}</td>

                            <td data-label="Название">
                                <textarea
                                    name="items[{{ $index }}][name]"
                                    class="input"
                                    rows="2"
                                    required
                                    style="min-width: 200px;"
                                >{{ old('items.'.$index.'.name', $item->name) }}</textarea>
                            </td>

                            <td data-label="Бренд">
                                <input
                                    type="text"
                                    name="items[{{ $index }}][brand]"
                                    class="input"
                                    value="{{ old('items.'.$index.'.brand', $item->brand) }}"
                                >
                            </td>

                            <td data-label="Артикул">
                                <input
                                    type="text"
                                    name="items[{{ $index }}][article]"
                                    class="input"
                                    value="{{ old('items.'.$index.'.article', $item->article) }}"
                                >
                            </td>

                            <td data-label="Кол-во">
                                <input
                                    type="number"
                                    name="items[{{ $index }}][quantity]"
                                    class="input"
                                    value="{{ old('items.'.$index.'.quantity', $item->quantity) }}"
                                    min="1"
                                    required
                                >
                            </td>

                            <td data-label="Ед. изм.">
                                <input
                                    type="text"
                                    name="items[{{ $index }}][unit]"
                                    class="input"
                                    value="{{ old('items.'.$index.'.unit', $item->unit) }}"
                                    required
                                >
                            </td>

                            <td data-label="Категория">
                                <select name="items[{{ $index }}][category]" class="input select" required>
                                    <option value="">Выберите категорию</option>
                                    @foreach($categories as $id => $name)
                                        <option value="{{ $name }}" {{ old('items.'.$index.'.category', $item->category) == $name ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            <td data-label="Тип оборудования">
                                <select name="items[{{ $index }}][product_type_id]" class="input select">
                                    <option value="">Не указан</option>
                                    @foreach($productTypes as $id => $name)
                                        <option value="{{ $id }}" {{ old('items.'.$index.'.product_type_id', $item->product_type_id) == $id ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            <td data-label="Область применения">
                                <select name="items[{{ $index }}][domain_id]" class="input select">
                                    <option value="">Не указана</option>
                                    @foreach($applicationDomains as $id => $name)
                                        <option value="{{ $id }}" {{ old('items.'.$index.'.domain_id', $item->domain_id) == $id ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            <td data-label="Описание">
                                <textarea
                                    name="items[{{ $index }}][description]"
                                    class="input"
                                    rows="2"
                                >{{ old('items.'.$index.'.description', $item->description) }}</textarea>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Кнопки действий -->
    <div style="display: flex; gap: var(--space-4); justify-content: flex-end; margin-bottom: var(--space-8);">
        <x-button tag="a" :href="route('admin.requests.show', $request->id)" variant="secondary">
            Отмена
        </x-button>
        <x-button type="submit" variant="success" icon="save">
            Сохранить изменения
        </x-button>
    </div>
</form>

@push('scripts')
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
@endpush
@endsection
