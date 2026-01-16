@extends('layouts.cabinet')

@section('title', 'Пользователь: ' . $user->name)

@section('content')
<x-page-header :title="$user->name" :description="$user->email">
    <x-slot name="actions">
        <x-button variant="secondary" :href="route('admin.users.index')" icon="arrow-left">
            Назад к списку
        </x-button>
        <x-button variant="accent" type="button" icon="wallet" onclick="openBalanceModal({{ $user->id }}, '{{ addslashes($user->name) }}', {{ $user->balance ?? 0 }})">
            Управление балансом
        </x-button>
    </x-slot>
</x-page-header>

<!-- Анкета пользователя -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title">Информация о пользователе</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6);">
            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Имя</div>
                <div style="font-size: var(--text-lg); font-weight: 600;">{{ $user->name }}</div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Email</div>
                <div style="font-size: var(--text-base);">{{ $user->email }}</div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Компания</div>
                <div style="font-size: var(--text-base);">{{ $user->company ?? '—' }}</div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Телефон</div>
                <div style="font-size: var(--text-base);">{{ $user->phone ?? '—' }}</div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Роль</div>
                <div>
                    @if($user->is_admin)
                        <x-badge variant="info" size="md">Администратор</x-badge>
                    @else
                        <x-badge variant="secondary" size="md">Пользователь</x-badge>
                    @endif
                </div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Sender ID</div>
                <div style="font-size: var(--text-base);">
                    @if($user->sender_id)
                        <span style="color: var(--success-600); font-weight: 600;">{{ $user->sender_id }}</span>
                        <x-button variant="secondary" size="sm" :href="route('admin.users.sender.show', $user)" icon="external-link" style="margin-left: var(--space-2);">
                            Управление
                        </x-button>
                    @else
                        <span style="color: var(--neutral-400);">Не настроен</span>
                    @endif
                </div>
            </div>

            <div>
                <div class="form-label" style="margin-bottom: var(--space-2); color: var(--neutral-500); font-size: var(--text-sm);">Дата регистрации</div>
                <div style="font-size: var(--text-base);">{{ $user->created_at->format('d.m.Y H:i') }}</div>
            </div>
        </div>
    </div>
</div>

<!-- Баланс и Тариф -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">
    <!-- Баланс -->
    <div class="card">
        <div class="card-header">
            <i data-lucide="wallet" style="width: 1.25rem; height: 1.25rem;"></i>
            Баланс
        </div>
        <div class="card-body">
            <div style="display: grid; gap: var(--space-4);">
                <div>
                    <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-bottom: var(--space-1);">Доступный баланс</div>
                    <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600);">
                        {{ number_format($user->available_balance, 2) }} ₽
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div>
                        <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-bottom: var(--space-1);">Заморожено</div>
                        <div style="font-size: var(--text-lg); font-weight: 600; color: var(--warning-600);">
                            {{ number_format($user->held_balance, 2) }} ₽
                        </div>
                    </div>

                    <div>
                        <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-bottom: var(--space-1);">Всего</div>
                        <div style="font-size: var(--text-lg); font-weight: 600;">
                            {{ number_format($user->balance, 2) }} ₽
                        </div>
                    </div>
                </div>

                <div style="padding-top: var(--space-3); border-top: 1px solid var(--neutral-200);">
                    <div style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-2);">Потрачено всего</div>
                    <div style="font-size: var(--text-xl); font-weight: 600; color: var(--neutral-700);">
                        {{ number_format($user->purchases_sum, 2) }} ₽
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Тариф -->
    <div class="card">
        <div class="card-header">
            <i data-lucide="zap" style="width: 1.25rem; height: 1.25rem;"></i>
            Тариф
        </div>
        <div class="card-body">
            @if($tariff)
                <div style="margin-bottom: var(--space-4);">
                    <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--primary-600); margin-bottom: var(--space-2);">
                        {{ $tariff->tariffPlan->name }}
                    </div>
                    @if($tariff->expires_at)
                        <div style="font-size: var(--text-sm); color: var(--neutral-600);">
                            Действует до {{ $tariff->expires_at->format('d.m.Y') }}
                        </div>
                    @endif
                </div>

                @if($limitsInfo)
                <div style="display: grid; gap: var(--space-4);">
                    <!-- Лимит позиций -->
                    @if($limitsInfo['items_limit'] !== null)
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                            <span style="font-size: var(--text-sm); color: var(--neutral-600);">Позиции</span>
                            <span style="font-size: var(--text-sm); font-weight: 600;">{{ $limitsInfo['items_used'] }} / {{ $limitsInfo['items_limit'] }}</span>
                        </div>
                        <div style="height: 8px; background: var(--neutral-200); border-radius: var(--radius-full); overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(90deg, var(--success-500), var(--primary-500)); width: {{ min(100, $limitsInfo['items_used_percentage'] ?? 0) }}%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    @endif

                    <!-- Лимит отчетов -->
                    @if($limitsInfo['reports_limit'] !== null)
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                            <span style="font-size: var(--text-sm); color: var(--neutral-600);">Отчеты</span>
                            <span style="font-size: var(--text-sm); font-weight: 600;">{{ $limitsInfo['reports_used'] }} / {{ $limitsInfo['reports_limit'] }}</span>
                        </div>
                        <div style="height: 8px; background: var(--neutral-200); border-radius: var(--radius-full); overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(90deg, var(--accent-500), var(--warning-500)); width: {{ min(100, $limitsInfo['reports_used_percentage'] ?? 0) }}%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    @endif

                    @if($limitsInfo['items_limit'] === null && $limitsInfo['reports_limit'] === null)
                    <div style="padding: var(--space-4); background: var(--neutral-50); border-radius: var(--radius-md); text-align: center;">
                        <div style="font-size: var(--text-sm); color: var(--neutral-600);">Без включенных кредитов</div>
                    </div>
                    @endif
                </div>
                @endif
            @else
                <x-empty-state icon="alert-circle" title="Нет активного тарифа" description="Пользователь не подключен к тарифному плану" />
            @endif
        </div>
    </div>
</div>

<!-- Статистика -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--primary-600); margin-bottom: var(--space-2);">
                {{ $requestsStats['total'] }}
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Всего заявок</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--success-600); margin-bottom: var(--space-2);">
                {{ $requestsStats['completed'] }}
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Завершено</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--warning-600); margin-bottom: var(--space-2);">
                {{ $requestsStats['pending'] }}
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">В обработке</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--accent-600); margin-bottom: var(--space-2);">
                {{ $reportAccessCount }}
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Отчетов открыто</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: var(--text-3xl); font-weight: 700; color: var(--info-600); margin-bottom: var(--space-2);">
                {{ $itemPurchasesCount }}
            </div>
            <div style="font-size: var(--text-sm); color: var(--neutral-600);">Позиций куплено</div>
        </div>
    </div>
</div>

<!-- Последние заявки -->
@if($user->requests->count() > 0)
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Последние заявки</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Номер</th>
                    <th>Название</th>
                    <th>Статус</th>
                    <th>Позиций</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($user->requests->take(10) as $request)
                <tr>
                    <td style="font-family: var(--font-mono); font-weight: 600;">{{ $request->request_number ?? $request->code }}</td>
                    <td>{{ $request->title ?? 'Без названия' }}</td>
                    <td>
                        @php
                            $statusVariant = match($request->status) {
                                'draft' => 'secondary',
                                'pending' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'info'
                            };
                            $statusText = \App\Models\Request::statuses()[$request->status] ?? $request->status;
                        @endphp
                        <x-badge :variant="$statusVariant" size="sm">{{ $statusText }}</x-badge>
                    </td>
                    <td>{{ $request->items_count ?? 0 }}</td>
                    <td style="color: var(--neutral-600); font-size: var(--text-sm);">{{ $request->created_at->format('d.m.Y H:i') }}</td>
                    <td>
                        <x-button variant="primary" size="sm" :href="route('admin.requests.show', $request)" icon="eye">
                            Открыть
                        </x-button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Balance Modal -->
<div id="balanceModal" class="modal-overlay" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-container">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <div style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: var(--accent-100); display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="wallet" style="width: 1.25rem; height: 1.25rem; color: var(--accent-600);"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: var(--text-lg); font-weight: 600;">Управление балансом</h3>
                        <p style="margin: 0; font-size: var(--text-sm); color: var(--neutral-600);" id="modalUserName"></p>
                    </div>
                </div>
                <button type="button" onclick="closeBalanceModal()" style="background: none; border: none; cursor: pointer; padding: var(--space-2); color: var(--neutral-600);">
                    <i data-lucide="x" style="width: 1.25rem; height: 1.25rem;"></i>
                </button>
            </div>

            <form id="balanceForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div style="padding: var(--space-4); background: var(--neutral-50); border-radius: var(--radius-md); margin-bottom: var(--space-4);">
                        <div style="font-size: var(--text-sm); color: var(--neutral-600);">Текущий баланс</div>
                        <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--accent-600);" id="modalCurrentBalance"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Действие</label>
                        <select name="action" class="select" required>
                            <option value="add">Пополнить баланс</option>
                            <option value="subtract">Списать с баланса</option>
                            <option value="set">Установить баланс</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Сумма (₽)</label>
                        <input type="number" name="amount" step="0.01" min="0" class="input" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <x-button type="button" variant="secondary" size="md" onclick="closeBalanceModal()">
                        Отмена
                    </x-button>
                    <x-button type="submit" variant="accent" size="md">
                        Сохранить
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openBalanceModal(userId, userName, currentBalance) {
    document.getElementById('modalUserName').textContent = userName;
    document.getElementById('modalCurrentBalance').textContent = parseFloat(currentBalance).toFixed(2) + ' ₽';
    document.getElementById('balanceForm').action = '/manage/users/' + userId + '/balance';
    document.getElementById('balanceModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    setTimeout(() => lucide.createIcons(), 100);
}

function closeBalanceModal() {
    document.getElementById('balanceModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('click', function(event) {
    const modal = document.getElementById('balanceModal');
    if (event.target === modal) {
        closeBalanceModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeBalanceModal();
    }
});

lucide.createIcons();
</script>
@endpush

@push('styles')
<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(2px);
    z-index: 9999;
    overflow-y: auto;
}

.modal-dialog {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-6);
}

.modal-container {
    background: var(--neutral-0);
    border-radius: var(--radius-xl);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 540px;
    width: 100%;
    position: relative;
}

.modal-header {
    padding: var(--space-6);
    border-bottom: 1px solid var(--neutral-200);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.modal-body {
    padding: var(--space-6);
}

.modal-footer {
    padding: var(--space-6);
    border-top: 1px solid var(--neutral-200);
    display: flex;
    justify-content: flex-end;
    gap: var(--space-3);
    background: var(--neutral-50);
    border-bottom-left-radius: var(--radius-xl);
    border-bottom-right-radius: var(--radius-xl);
}
</style>
@endpush
@endsection
