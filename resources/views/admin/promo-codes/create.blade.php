@extends('layouts.cabinet')

@section('title', 'Генерация промокодов')

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <x-page-header
        title="Генерация промокодов"
        description="Создайте промокоды для раздачи пользователям"
    />

    <div class="alert alert-info" style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: start; gap: var(--space-3);">
            <i data-lucide="info" class="icon-md"></i>
            <div>
                <strong>Как это работает</strong>
                <p style="margin-top: var(--space-1); margin-bottom: 0;">
                    Промокоды дают дополнительный баланс пользователям и приоритетное право создавать заявки.
                    После генерации вы сможете экспортировать коды в CSV и распространить их среди пользователей.
                </p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600; display: flex; align-items: center; gap: var(--space-2);">
                <i data-lucide="gift" class="icon-md"></i>
                Параметры генерации
            </h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.promo-codes.store') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="count">
                        Количество промокодов
                    </label>
                    <input
                        type="number"
                        id="count"
                        name="count"
                        value="{{ old('count', 10) }}"
                        min="1"
                        max="1000"
                        class="input @error('count') is-invalid @enderror"
                        required
                    >
                    @error('count')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-help">
                        Укажите, сколько промокодов нужно создать (от 1 до 1000)
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="amount">
                        Сумма каждого промокода (₽)
                    </label>
                    <input
                        type="number"
                        id="amount"
                        name="amount"
                        value="{{ old('amount', 300) }}"
                        step="0.01"
                        min="0"
                        class="input @error('amount') is-invalid @enderror"
                        required
                    >
                    @error('amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-help">
                        Сумма, которая будет зачислена на баланс пользователя при активации промокода
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">
                        Описание (необязательно)
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        class="input @error('description') is-invalid @enderror"
                        placeholder="Например: Промокоды для конференции X, акция Y..."
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-help">
                        Опишите назначение этих промокодов для внутреннего учета
                    </small>
                </div>

                <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                    <x-button type="submit" variant="accent" icon="check">
                        Сгенерировать промокоды
                    </x-button>
                    <x-button
                        variant="secondary"
                        href="{{ route('admin.promo-codes.index') }}"
                    >
                        Отмена
                    </x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Предпросмотр -->
    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <h2 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">
                Примеры промокодов
            </h2>
        </div>
        <div class="card-body">
            <p style="margin-bottom: var(--space-3); color: var(--text-secondary);">
                Промокоды будут сгенерированы в таком формате:
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                <code style="background: var(--surface-secondary); padding: 0.5rem 1rem; border-radius: 6px; font-family: monospace; font-weight: 600; font-size: 1rem;">
                    ABC12345
                </code>
                <code style="background: var(--surface-secondary); padding: 0.5rem 1rem; border-radius: 6px; font-family: monospace; font-weight: 600; font-size: 1rem;">
                    XYZ98765
                </code>
                <code style="background: var(--surface-secondary); padding: 0.5rem 1rem; border-radius: 6px; font-family: monospace; font-weight: 600; font-size: 1rem;">
                    QWE45678
                </code>
            </div>
            <p style="margin-top: var(--space-3); font-size: 0.875rem; color: var(--text-tertiary);">
                Каждый код уникален и состоит из 8 символов (заглавные буквы и цифры)
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    lucide.createIcons();

    // Расчет общей суммы
    const countInput = document.getElementById('count');
    const amountInput = document.getElementById('amount');

    function updateTotal() {
        const count = parseInt(countInput.value) || 0;
        const amount = parseFloat(amountInput.value) || 0;
        const total = count * amount;

        console.log(`Будет создано ${count} промокодов на общую сумму ${total.toFixed(2)} ₽`);
    }

    countInput?.addEventListener('input', updateTotal);
    amountInput?.addEventListener('input', updateTotal);
</script>
@endpush
