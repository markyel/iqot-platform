@extends('layouts.cabinet')

@section('title', 'Новая заявка')
@section('header', 'Создание заявки')

@section('content')
<div style="max-width: 900px;">
    <div style="background: white; border-radius: 0.75rem; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        
        <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 1rem; margin-bottom: 2rem; border-radius: 0.375rem;">
            <h3 style="font-weight: 600; margin-bottom: 0.5rem;">Требования к заявке</h3>
            <ul style="padding-left: 1.25rem; color: #374151; line-height: 1.75;">
                <li><strong>Тематика:</strong> запчасти для лифтов и эскалаторов</li>
                <li><strong>Контактная информация:</strong> обязательно укажите все данные организации</li>
                <li><strong>Для каждой позиции:</strong> полное название, тип оборудования, марка и артикул производителя</li>
            </ul>
        </div>

        <form action="{{ route('cabinet.requests.store') }}" method="POST" id="requestForm">
            @csrf
            
            <!-- Контактная информация -->
            <section style="margin-bottom: 2.5rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: #111827;">
                    Контактная информация
                </h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div style="grid-column: span 2;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">
                            Название организации <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="company_name" value="{{ old('company_name') }}" required
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9375rem;"
                            placeholder="ООО &quot;Название&quot;">
                    </div>
                    
                    <div style="grid-column: span 2;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">
                            Адрес <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="company_address" value="{{ old('company_address') }}" required
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9375rem;"
                            placeholder="г. Москва, ул. Примерная, д. 1">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">
                            ИНН <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="inn" value="{{ old('inn') }}" required pattern="\d{10,12}" maxlength="12"
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9375rem;"
                            placeholder="1234567890">
                        <small style="color: #6b7280; font-size: 0.875rem;">10 или 12 цифр</small>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">
                            КПП
                        </label>
                        <input type="text" name="kpp" value="{{ old('kpp') }}" pattern="\d{9}" maxlength="9"
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9375rem;"
                            placeholder="123456789">
                        <small style="color: #6b7280; font-size: 0.875rem;">9 цифр (если есть)</small>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">
                            ФИО ответственного <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="contact_person" value="{{ old('contact_person') }}" required
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9375rem;"
                            placeholder="Иванов Иван Иванович">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">
                            Телефон <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="tel" name="contact_phone" value="{{ old('contact_phone') }}" required
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9375rem;"
                            placeholder="+7 (999) 123-45-67">
                    </div>
                </div>
            </section>
            
            <!-- Позиции заявки -->
            <section style="margin-bottom: 2rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: #111827;">
                    Позиции заявки
                </h2>
                
                <div id="itemsContainer">
                    <!-- Будут добавляться позиции -->
                </div>
                
                <button type="button" onclick="addItem()" class="btn" style="background: #f3f4f6; color: #374151; margin-top: 1rem;">
                    + Добавить позицию
                </button>
            </section>
            
            <div style="display: flex; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                <button type="submit" class="btn btn-primary">
                    Создать заявку
                </button>
                <a href="{{ route('cabinet.requests') }}" class="btn" style="background: #f3f4f6; color: #374151;">
                    Отмена
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
let itemCounter = 0;

function addItem() {
    const container = document.getElementById('itemsContainer');
    const itemHtml = `
        <div class="request-item" style="background: #f9fafb; padding: 1.5rem; border-radius: 0.5rem; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h4 style="font-weight: 600;">Позиция ${itemCounter + 1}</h4>
                <button type="button" onclick="removeItem(this)" style="color: #ef4444; background: none; border: none; cursor: pointer; font-size: 1.25rem;">
                    ×
                </button>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div style="grid-column: span 2;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">
                        Полное название <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="items[${itemCounter}][name]" required
                        style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;"
                        placeholder="Например: Кнопка вызова лифта с подсветкой">
                </div>
                
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">
                        Тип оборудования <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="items[${itemCounter}][equipment_type]" required
                        style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;">
                        <option value="">Выберите...</option>
                        <option value="lift">Лифт</option>
                        <option value="escalator">Эскалатор</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">
                        Марка оборудования <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="items[${itemCounter}][equipment_brand]" required
                        style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;"
                        placeholder="Otis, Schindler, Kone...">
                </div>
                
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">
                        Артикул производителя <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="items[${itemCounter}][manufacturer_article]" required
                        style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;"
                        placeholder="XAA177AK1">
                </div>
                
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.875rem;">
                        Количество
                    </label>
                    <input type="number" name="items[${itemCounter}][quantity]" min="1" value="1"
                        style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;">
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
});
</script>
@endpush
@endsection
