@extends('layouts.cabinet')

@section('title', 'Управление пользователями')

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    <x-page-header
        title="Управление пользователями"
        description="Управление аккаунтами, балансами и настройками отправителей"
    />

    @if(session('success'))
        <div class="alert alert-success">
            <i data-lucide="check-circle"></i>
            {{ session('success') }}
        </div>
    @endif

    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; align-items: end;">
                <div class="form-group">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Имя, email, компания..." class="input">
                </div>

                <div class="form-group">
                    <label class="form-label">Роль</label>
                    <select name="is_admin" class="select">
                        <option value="">Все пользователи</option>
                        <option value="1" {{ request('is_admin') === '1' ? 'selected' : '' }}>Администраторы</option>
                        <option value="0" {{ request('is_admin') === '0' ? 'selected' : '' }}>Пользователи</option>
                    </select>
                </div>

                <x-button type="submit" variant="primary">Применить фильтры</x-button>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card" style="margin-top: var(--space-6);">
        <table class="table">
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>Пользователь</th>
                <th>Компания</th>
                <th style="width: 100px;">Роль</th>
                <th style="width: 80px;">Sender</th>
                <th style="width: 120px;">Баланс</th>
                <th style="width: 100px;">Покупок</th>
                <th style="width: 120px;">Потрачено</th>
                <th style="width: 150px;">Дата регистрации</th>
                <th style="width: 120px;">Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td data-label="ID" style="color: var(--text-secondary); font-family: var(--font-mono);">{{ $user->id }}</td>
                <td data-label="Пользователь">
                    <div style="font-weight: 600; color: var(--text-primary);">{{ $user->name }}</div>
                    <div style="font-size: 0.875rem; color: var(--text-secondary);">{{ $user->email }}</div>
                    @if($user->phone)
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            <i data-lucide="phone" style="width: 14px; height: 14px;"></i>
                            {{ $user->phone }}
                        </div>
                    @endif
                </td>
                <td data-label="Компания" style="color: var(--text-primary);">
                    {{ $user->company ?? '—' }}
                </td>
                <td data-label="Роль">
                    @if($user->is_admin)
                        <x-badge variant="info">Админ</x-badge>
                    @else
                        <x-badge variant="secondary">Пользователь</x-badge>
                    @endif
                </td>
                <td data-label="Sender" style="text-align: center;">
                    @if($user->sender_id)
                        <i data-lucide="check-circle" style="color: var(--success); width: 20px; height: 20px;" title="Email-отправитель настроен"></i>
                    @else
                        <span style="color: var(--border); font-size: 1.25rem;">—</span>
                    @endif
                </td>
                <td data-label="Баланс" style="font-weight: 600; color: var(--success);">
                    {{ number_format($user->balance ?? 0, 2) }} ₽
                </td>
                <td data-label="Покупок" style="text-align: center;">
                    {{ $user->purchases_count }}
                </td>
                <td data-label="Потрачено" style="font-weight: 600; color: var(--text-secondary);">
                    {{ number_format($user->purchases_sum, 2) }} ₽
                </td>
                <td data-label="Дата регистрации" style="color: var(--text-secondary); font-size: 0.875rem;">
                    {{ $user->created_at->format('d.m.Y') }}
                </td>
                <td data-label="Действия">
                    <div style="display: flex; gap: var(--space-2);">
                        <x-button
                            type="button"
                            variant="primary"
                            size="sm"
                            icon="wallet"
                            onclick="openBalanceModal({{ $user->id }}, '{{ addslashes($user->name) }}', {{ $user->balance ?? 0 }})"
                        >
                            Баланс
                        </x-button>
                        <x-button
                            :href="route('admin.users.sender.show', $user)"
                            variant="secondary"
                            size="sm"
                            icon="mail"
                        >
                            Sender
                        </x-button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10">
                    <x-empty-state
                        icon="users"
                        title="Пользователи не найдены"
                        description="Попробуйте изменить параметры поиска"
                    />
                </td>
            </tr>
            @endforelse
        </tbody>
        </table>

        <!-- Pagination -->
        @if($users->hasPages())
        <div style="padding: var(--space-4); border-top: 1px solid var(--neutral-200);">
            <div class="pagination">
            @if ($users->onFirstPage())
                <span class="pagination-item disabled">
                    <i data-lucide="chevron-left"></i>
                    Назад
                </span>
            @else
                <a href="{{ $users->previousPageUrl() }}" class="pagination-item">
                    <i data-lucide="chevron-left"></i>
                    Назад
                </a>
            @endif

            @foreach ($users->getUrlRange(1, $users->lastPage()) as $page => $url)
                @if ($page == $users->currentPage())
                    <span class="pagination-item active">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="pagination-item">{{ $page }}</a>
                @endif
            @endforeach

            @if ($users->hasMorePages())
                <a href="{{ $users->nextPageUrl() }}" class="pagination-item">
                    Вперёд
                    <i data-lucide="chevron-right"></i>
                </a>
            @else
                <span class="pagination-item disabled">
                    Вперёд
                    <i data-lucide="chevron-right"></i>
                </span>
            @endif
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Balance Modal -->
<div id="balanceModal" class="modal">
    <div class="modal-content">
        <h2 class="modal-title">
            <i data-lucide="wallet"></i>
            Управление балансом
        </h2>
        <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius);">
            <div style="font-size: 0.875rem; color: var(--text-secondary);">Пользователь</div>
            <div style="font-weight: 600; color: var(--text-primary);" id="modalUserName"></div>
            <div style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.5rem;">Текущий баланс</div>
            <div style="font-weight: 700; color: var(--success); font-size: 1.25rem;" id="modalCurrentBalance"></div>
        </div>

        <form id="balanceForm" method="POST">
            @csrf
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

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <x-button type="submit" variant="primary" style="flex: 1;">Сохранить</x-button>
                <x-button type="button" variant="secondary" style="flex: 1;" onclick="closeBalanceModal()">Отмена</x-button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openBalanceModal(userId, userName, currentBalance) {
    document.getElementById('modalUserName').textContent = userName;
    document.getElementById('modalCurrentBalance').textContent = parseFloat(currentBalance).toFixed(2) + ' ₽';
    document.getElementById('balanceForm').action = '/manage/users/' + userId + '/balance';
    document.getElementById('balanceModal').classList.add('active');
}

function closeBalanceModal() {
    document.getElementById('balanceModal').classList.remove('active');
}

// Close modal on outside click
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();

    var modal = document.getElementById('balanceModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeBalanceModal();
            }
        });
    }
});
</script>
@endpush
@endsection
