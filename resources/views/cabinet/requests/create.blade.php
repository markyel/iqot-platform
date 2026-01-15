@extends('layouts.cabinet')

@section('title', 'Новая заявка')

@section('content')
<x-page-header
    title="Создание заявки"
    subtitle=""
/>

<div style="max-width: 900px;">
    <div class="alert alert-info" style="margin-bottom: var(--space-6);">
        <h3 style="font-weight: 600; margin-bottom: var(--space-2); display: flex; align-items: center; gap: var(--space-2);">
            <i data-lucide="info" style="width: 20px; height: 20px;"></i>
            Требования к заявке
        </h3>
        <ul style="padding-left: var(--space-5); line-height: 1.75; margin: 0;">
            <li><strong>Тематика:</strong> запчасти для лифтов и эскалаторов</li>
            <li><strong>Контактная информация:</strong> обязательно укажите все данные организации</li>
            <li><strong>Для каждой позиции:</strong> полное название, тип оборудования, марка и артикул производителя</li>
        </ul>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('cabinet.requests.store') }}" method="POST" id="requestForm">
                @csrf

                <!-- Контактная информация -->
                <section style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: var(--space-4);">
                        Контактная информация
                    </h2>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">
                            Название организации <span style="color: var(--red-600);">*</span>
                        </label>
                        <input
                            type="text"
                            name="company_name"
                            value="{{ old('company_name') }}"
                            required
                            class="input"
                            placeholder="ООО &quot;Название&quot;"
                        >
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">
                            Адрес <span style="color: var(--red-600);">*</span>
                        </label>
                        <input
                            type="text"
                            name="company_address"
                            value="{{ old('company_address') }}"
                            required
                            class="input"
                            placeholder="г. Москва, ул. Примерная, д. 1"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            ИНН <span style="color: var(--red-600);">*</span>
                        </label>
                        <input
                            type="text"
                            name="inn"
                            value="{{ old('inn') }}"
                            required
                            pattern="\d{10,12}"
                            maxlength="12"
                            class="input"
                            placeholder="1234567890"
                        >
                        <small class="text-muted" style="font-size: 0.875rem;">10 или 12 цифр</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            КПП
                        </label>
                        <input
                            type="text"
                            name="kpp"
                            value="{{ old('kpp') }}"
                            pattern="\d{9}"
                            maxlength="9"
                            class="input"
                            placeholder="123456789"
                        >
                        <small class="text-muted" style="font-size: 0.875rem;">9 цифр (если есть)</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            ФИО ответственного <span style="color: var(--red-600);">*</span>
                        </label>
                        <input
                            type="text"
                            name="contact_person"
                            value="{{ old('contact_person') }}"
                            required
                            class="input"
                            placeholder="Иванов Иван Иванович"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Телефон <span style="color: var(--red-600);">*</span>
                        </label>
                        <input
                            type="tel"
                            name="contact_phone"
                            value="{{ old('contact_phone') }}"
                            required
                            class="input"
                            placeholder="+7 (999) 123-45-67"
                        >
                        </div>
                    </div>
                </section>

                <!-- Позиции заявки -->
                <section style="margin-bottom: var(--space-5);">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: var(--space-4);">
                        Позиции заявки
                    </h2>

                    <div id="itemsContainer">
                        <!-- Будут добавляться позиции -->
                    </div>

                    <x-button
                        type="button"
                        variant="secondary"
                        icon="plus"
                        onclick="addItem()"
                    >
                        Добавить позицию
                    </x-button>
                </section>

                <div style="display: flex; gap: var(--space-3); padding-top: var(--space-4); border-top: 1px solid var(--neutral-200);">
                    <x-button type="submit" variant="primary">
                        Создать заявку
                    </x-button>
                    <x-button
                        href="{{ route('cabinet.requests') }}"
                        variant="secondary"
                    >
                        Отмена
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let itemCounter = 0;

function addItem() {
    const container = document.getElementById('itemsContainer');
    const itemHtml = `
        <div class="request-item" style="background: var(--neutral-50); padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-3); border: 1px solid var(--neutral-200);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-3);">
                <h4 style="font-weight: 600; margin: 0;">Позиция ${itemCounter + 1}</h4>
                <button type="button" onclick="removeItem(this)" style="color: var(--red-600); background: none; border: none; cursor: pointer; font-size: 1.5rem; line-height: 1; padding: 0; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); transition: background-color 0.2s;" onmouseover="this.style.background='var(--red-50)'" onmouseout="this.style.background='none'">
                    ×
                </button>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">
                        Полное название <span style="color: var(--red-600);">*</span>
                    </label>
                    <input
                        type="text"
                        name="items[${itemCounter}][name]"
                        required
                        class="input"
                        placeholder="Например: Кнопка вызова лифта с подсветкой"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Тип оборудования <span style="color: var(--red-600);">*</span>
                    </label>
                    <select name="items[${itemCounter}][equipment_type]" required class="select">
                        <option value="">Выберите...</option>
                        <option value="lift">Лифт</option>
                        <option value="escalator">Эскалатор</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Марка оборудования <span style="color: var(--red-600);">*</span>
                    </label>
                    <input
                        type="text"
                        name="items[${itemCounter}][equipment_brand]"
                        required
                        class="input"
                        placeholder="Otis, Schindler, Kone..."
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Артикул производителя <span style="color: var(--red-600);">*</span>
                    </label>
                    <input
                        type="text"
                        name="items[${itemCounter}][manufacturer_article]"
                        required
                        class="input"
                        placeholder="XAA177AK1"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Количество
                    </label>
                    <input
                        type="number"
                        name="items[${itemCounter}][quantity]"
                        min="1"
                        value="1"
                        class="input"
                    >
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', itemHtml);
    itemCounter++;
}

function removeItem(btn) {
    btn.closest('.request-item').remove();
}

// Добавляем первую позицию при загрузке
document.addEventListener('DOMContentLoaded', function() {
    addItem();
    lucide.createIcons();
});
</script>
@endpush
@endsection
