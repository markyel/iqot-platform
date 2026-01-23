@extends('layouts.cabinet')

@section('title', 'Реквизиты для биллинга')

@section('content')
<x-page-header
    title="Реквизиты для биллинга"
    description="Управление реквизитами исполнителя для счетов и актов"
/>
<div style="max-width: 1000px; margin: 0 auto;">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom: var(--space-4);">
            <strong>Ошибки в форме:</strong>
            <ul style="margin: var(--space-2) 0 0 var(--space-4); padding: 0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info" style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: start; gap: var(--space-3);">
            <i data-lucide="info" class="icon-md"></i>
            <div>
                <strong>Информация</strong>
                <p style="margin-top: var(--space-1); margin-bottom: 0;">
                    Эти реквизиты будут использоваться при формировании счетов на оплату и закрывающих документов (УПД).
                    Заполните все поля корректно, так как они будут отображаться в официальных документах.
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.billing.settings.update') }}" enctype="multipart/form-data">
        @csrf

        {{-- Общие данные --}}
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="building" class="icon-md"></i>
                    Общие данные
                </h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="name">
                        Название <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $settings->name) }}"
                        class="input"
                        placeholder="ИП Маркелов Дмитрий Евгеньевич"
                        required
                    >
                    <small class="form-help">
                        Краткое название (отображается в шапке документов)
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">
                        Полное название
                    </label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="{{ old('full_name', $settings->full_name) }}"
                        class="input"
                        placeholder="Индивидуальный предприниматель Маркелов Дмитрий Евгеньевич"
                    >
                    <small class="form-help">
                        Полное наименование (опционально)
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">
                        Юридический адрес <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="address"
                        name="address"
                        class="input"
                        rows="2"
                        placeholder="127549, г. Москва, ш. Алтуфьевское, д. 62А, кв. 97"
                        required
                    >{{ old('address', $settings->address) }}</textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="inn">
                            ИНН <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="inn"
                            name="inn"
                            value="{{ old('inn', $settings->inn) }}"
                            class="input"
                            placeholder="771512090267"
                            maxlength="12"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kpp">
                            КПП
                        </label>
                        <input
                            type="text"
                            id="kpp"
                            name="kpp"
                            value="{{ old('kpp', $settings->kpp) }}"
                            class="input"
                            placeholder="Для юридических лиц"
                            maxlength="9"
                        >
                        <small class="form-help">Только для юридических лиц</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ogrnip">
                            ОГРНИП
                        </label>
                        <input
                            type="text"
                            id="ogrnip"
                            name="ogrnip"
                            value="{{ old('ogrnip', $settings->ogrnip) }}"
                            class="input"
                            placeholder="324774600503025"
                            maxlength="15"
                        >
                        <small class="form-help">Для индивидуальных предпринимателей</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ogrn">
                            ОГРН
                        </label>
                        <input
                            type="text"
                            id="ogrn"
                            name="ogrn"
                            value="{{ old('ogrn', $settings->ogrn) }}"
                            class="input"
                            placeholder="Для юридических лиц"
                            maxlength="13"
                        >
                        <small class="form-help">Только для юридических лиц</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="registration_date">
                            Дата регистрации
                        </label>
                        <input
                            type="date"
                            id="registration_date"
                            name="registration_date"
                            value="{{ old('registration_date', $settings->registration_date?->format('Y-m-d')) }}"
                            class="input"
                        >
                    </div>
                </div>
            </div>
        </div>

        {{-- Банковские реквизиты --}}
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="landmark" class="icon-md"></i>
                    Банковские реквизиты
                </h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="bank_name">
                        Название банка <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="bank_name"
                        name="bank_name"
                        value="{{ old('bank_name', $settings->bank_name) }}"
                        class="input"
                        placeholder='АО "ТИНЬКОФФ БАНК"'
                        required
                    >
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="bank_bik">
                            БИК <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="bank_bik"
                            name="bank_bik"
                            value="{{ old('bank_bik', $settings->bank_bik) }}"
                            class="input"
                            placeholder="044525974"
                            maxlength="9"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="bank_corr_account">
                            Корреспондентский счет <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="bank_corr_account"
                            name="bank_corr_account"
                            value="{{ old('bank_corr_account', $settings->bank_corr_account) }}"
                            class="input"
                            placeholder="30101810145250000974"
                            maxlength="20"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bank_account">
                        Расчетный счет <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="bank_account"
                        name="bank_account"
                        value="{{ old('bank_account', $settings->bank_account) }}"
                        class="input"
                        placeholder="40802810100000000000"
                        maxlength="20"
                        required
                    >
                </div>
            </div>
        </div>

        {{-- Подписанты --}}
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="user-check" class="icon-md"></i>
                    Подписанты
                </h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="director_name">
                        ФИО руководителя (полное)
                    </label>
                    <input
                        type="text"
                        id="director_name"
                        name="director_name"
                        value="{{ old('director_name', $settings->director_name) }}"
                        class="input"
                        placeholder="Маркелов Дмитрий Евгеньевич"
                    >
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="director_short">
                            ФИО руководителя (краткое)
                        </label>
                        <input
                            type="text"
                            id="director_short"
                            name="director_short"
                            value="{{ old('director_short', $settings->director_short) }}"
                            class="input"
                            placeholder="Маркелов Д. Е."
                        >
                        <small class="form-help">Формат: Фамилия И. О.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="director_position">
                            Должность руководителя
                        </label>
                        <input
                            type="text"
                            id="director_position"
                            name="director_position"
                            value="{{ old('director_position', $settings->director_position) }}"
                            class="input"
                            placeholder="Индивидуальный предприниматель"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="accountant_name">
                        ФИО главного бухгалтера
                    </label>
                    <input
                        type="text"
                        id="accountant_name"
                        name="accountant_name"
                        value="{{ old('accountant_name', $settings->accountant_name) }}"
                        class="input"
                        placeholder="Оставьте пустым, если совпадает с руководителем"
                    >
                </div>
            </div>
        </div>

        {{-- Контактные данные --}}
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="mail" class="icon-md"></i>
                    Контактные данные
                </h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="email">
                            Email
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $settings->email) }}"
                            class="input"
                            placeholder="info@iqot.ru"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">
                            Телефон
                        </label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="{{ old('phone', $settings->phone) }}"
                            class="input"
                            placeholder="+7 (999) 123-45-67"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="website">
                        Веб-сайт
                    </label>
                    <input
                        type="text"
                        id="website"
                        name="website"
                        value="{{ old('website', $settings->website) }}"
                        class="input"
                        placeholder="iqot.ru"
                    >
                </div>
            </div>
        </div>

        {{-- Подпись и печать --}}
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="file-signature" class="icon-md"></i>
                    Подпись и печать
                </h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="signature_image">
                            Изображение подписи
                        </label>
                        <input
                            type="file"
                            id="signature_image"
                            name="signature_image"
                            class="input"
                            accept="image/png,image/jpeg,image/jpg"
                        >
                        <small class="form-help">PNG, JPG до 2 МБ. Рекомендуемый размер: 200x80px</small>
                        @if($settings->signature_image)
                            <div style="margin-top: var(--space-2); padding: var(--space-2); background: var(--neutral-50); border-radius: 4px;">
                                <img src="{{ asset('storage/' . $settings->signature_image) }}" alt="Подпись" style="max-width: 200px; height: auto;">
                                <div style="margin-top: var(--space-2);">
                                    <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--text-sm);">
                                        <input type="checkbox" name="remove_signature" value="1">
                                        Удалить изображение
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="stamp_image">
                            Изображение печати
                        </label>
                        <input
                            type="file"
                            id="stamp_image"
                            name="stamp_image"
                            class="input"
                            accept="image/png,image/jpeg,image/jpg"
                        >
                        <small class="form-help">PNG, JPG до 2 МБ. Рекомендуемый размер: 150x150px</small>
                        @if($settings->stamp_image)
                            <div style="margin-top: var(--space-2); padding: var(--space-2); background: var(--neutral-50); border-radius: 4px;">
                                <img src="{{ asset('storage/' . $settings->stamp_image) }}" alt="Печать" style="max-width: 150px; height: auto;">
                                <div style="margin-top: var(--space-2);">
                                    <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--text-sm);">
                                        <input type="checkbox" name="remove_stamp" value="1">
                                        Удалить изображение
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- НДС --}}
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="percent" class="icon-md"></i>
                    Настройки НДС
                </h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                        <input
                            type="checkbox"
                            name="vat_enabled"
                            value="1"
                            {{ old('vat_enabled', $settings->vat_enabled ?? true) ? 'checked' : '' }}
                            onchange="document.getElementById('vat_rate_field').style.display = this.checked ? 'block' : 'none'"
                        >
                        <span>Включать НДС в счета</span>
                    </label>
                    <small class="form-help">Если выключено, счета будут без НДС</small>
                </div>

                <div id="vat_rate_field" class="form-group" style="display: {{ old('vat_enabled', $settings->vat_enabled ?? true) ? 'block' : 'none' }};">
                    <label class="form-label" for="vat_rate">
                        Ставка НДС (%) <span class="text-danger">*</span>
                    </label>
                    <input
                        type="number"
                        id="vat_rate"
                        name="vat_rate"
                        value="{{ old('vat_rate', $settings->vat_rate ?? 20) }}"
                        class="input"
                        step="0.01"
                        min="0"
                        max="100"
                        placeholder="20"
                    >
                    <small class="form-help">Стандартные ставки: 0%, 10%, 20%</small>
                </div>
            </div>
        </div>

        {{-- Нумерация счетов --}}
        <div class="card" style="margin-bottom: var(--space-6);">
            <div class="card-header">
                <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                    <i data-lucide="hash" class="icon-md"></i>
                    Нумерация счетов
                </h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info" style="margin-bottom: var(--space-4);">
                    <strong>Доступные переменные в маске:</strong>
                    <ul style="margin: var(--space-2) 0 0 var(--space-4); padding: 0;">
                        <li><code>{NUMBER}</code> — номер счёта</li>
                        <li><code>{YYYY}</code> — год (4 цифры)</li>
                        <li><code>{YY}</code> — год (2 цифры)</li>
                        <li><code>{MM}</code> — месяц (2 цифры)</li>
                        <li><code>{DD}</code> — день (2 цифры)</li>
                    </ul>
                    <p style="margin-top: var(--space-2); margin-bottom: 0;">
                        Примеры: <code>{NUMBER}</code> → 611054, <code>INV-{YYYY}-{NUMBER}</code> → INV-2026-611054
                    </p>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="invoice_number_mask">
                            Маска номера счёта <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="invoice_number_mask"
                            name="invoice_number_mask"
                            value="{{ old('invoice_number_mask', $settings->invoice_number_mask) }}"
                            class="input"
                            placeholder="{NUMBER}"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="invoice_number_start">
                            Начальный номер <span class="text-danger">*</span>
                        </label>
                        <input
                            type="number"
                            id="invoice_number_start"
                            name="invoice_number_start"
                            value="{{ old('invoice_number_start', $settings->invoice_number_start) }}"
                            class="input"
                            min="1"
                            required
                        >
                    </div>
                </div>

                @if($settings->invoice_number_current > 0)
                    <div class="alert alert-warning">
                        <strong>Текущий номер счетчика:</strong> {{ $settings->invoice_number_current }}<br>
                        <small>Изменение начального номера не влияет на уже выставленные счета</small>
                    </div>
                @endif
            </div>
        </div>

        {{-- Кнопки --}}
        <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
            <x-button type="submit" variant="primary">
                Сохранить реквизиты
            </x-button>
        </div>
    </form>
</div>
@endsection
