@extends('layouts.cabinet')

@section('title', 'Управление пользователями')

@push('styles')
<style>
    /* Light theme for admin */
    .admin-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .admin-table {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
    }

    .admin-table thead {
        background: #f9fafb;
    }

    .admin-table th {
        text-align: left;
        padding: 1rem 1.5rem;
        color: #6b7280;
        font-weight: 600;
        font-size: 0.875rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .admin-table td {
        padding: 1rem 1.5rem;
        border-top: 1px solid #f3f4f6;
    }

    .admin-table tbody tr:hover {
        background: #f9fafb;
    }

    .form-input, .form-select {
        background: #ffffff;
        border: 1px solid #d1d5db;
        color: #111827;
        padding: 0.625rem 1rem;
        border-radius: 8px;
        outline: none;
    }

    .form-input:focus, .form-select:focus {
        border-color: #10b981;
    }

    .btn-green {
        background: #10b981;
        color: white;
        padding: 0.625rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-green:hover {
        background: #059669;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }

    .badge-admin {
        background: #dbeafe;
        color: #1e40af;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-user {
        background: #f3f4f6;
        color: #6b7280;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: #ffffff;
        border-radius: 12px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .pagination {
        display: flex;
        gap: 0.5rem;
        margin-top: 1.5rem;
    }

    .pagination a, .pagination span {
        padding: 0.5rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        color: #374151;
        text-decoration: none;
        background: #ffffff;
    }

    .pagination .active {
        background: #10b981;
        color: white;
        border-color: #10b981;
    }

    .pagination a:hover:not(.active) {
        background: #f9fafb;
    }
</style>
@endpush

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #111827;">
            👥 Управление пользователями
        </h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Filters -->
    <div class="admin-card">
        <form method="GET" action="{{ route('admin.users.index') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; align-items: end;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: #374151;">Поиск</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Имя, email, компания..." class="form-input" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: #374151;">Роль</label>
                <select name="is_admin" class="form-select" style="width: 100%;">
                    <option value="">Все пользователи</option>
                    <option value="1" {{ request('is_admin') === '1' ? 'selected' : '' }}>Администраторы</option>
                    <option value="0" {{ request('is_admin') === '0' ? 'selected' : '' }}>Пользователи</option>
                </select>
            </div>

            <button type="submit" class="btn-green">Применить фильтры</button>
        </form>
    </div>

    <!-- Users Table -->
    <table class="admin-table">
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
                <td style="color: #6b7280; font-family: monospace;">{{ $user->id }}</td>
                <td>
                    <div style="font-weight: 600; color: #111827;">{{ $user->name }}</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">{{ $user->email }}</div>
                    @if($user->phone)
                        <div style="font-size: 0.875rem; color: #6b7280;">📞 {{ $user->phone }}</div>
                    @endif
                </td>
                <td style="color: #374151;">
                    {{ $user->company ?? '—' }}
                </td>
                <td>
                    @if($user->is_admin)
                        <span class="badge-admin">Админ</span>
                    @else
                        <span class="badge-user">Пользователь</span>
                    @endif
                </td>
                <td style="text-align: center;">
                    @if($user->sender_id)
                        <span style="color: #10b981; font-size: 1.25rem;" title="Email-отправитель настроен">✓</span>
                    @else
                        <span style="color: #d1d5db; font-size: 1.25rem;" title="Email-отправитель не настроен">—</span>
                    @endif
                </td>
                <td style="font-weight: 600; color: #10b981;">
                    {{ number_format($user->balance ?? 0, 2) }} ₽
                </td>
                <td style="text-align: center;">
                    {{ $user->purchases_count }}
                </td>
                <td style="font-weight: 600; color: #6b7280;">
                    {{ number_format($user->purchases_sum, 2) }} ₽
                </td>
                <td style="color: #6b7280; font-size: 0.875rem;">
                    {{ $user->created_at->format('d.m.Y') }}
                </td>
                <td>
                    <div style="display: flex; gap: 0.5rem; flex-direction: column;">
                        <button onclick="openBalanceModal({{ $user->id }}, '{{ addslashes($user->name) }}', {{ $user->balance ?? 0 }})" class="btn-green btn-sm">
                            Баланс
                        </button>
                        <a href="{{ route('admin.users.sender.show', $user) }}" class="btn-green btn-sm" style="text-align: center; text-decoration: none;">
                            Sender
                        </a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" style="text-align: center; padding: 3rem; color: #9ca3af;">
                    Пользователи не найдены
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Pagination -->
    @if($users->hasPages())
        <div class="pagination">
            @if ($users->onFirstPage())
                <span style="opacity: 0.5;">← Назад</span>
            @else
                <a href="{{ $users->previousPageUrl() }}">← Назад</a>
            @endif

            @foreach ($users->getUrlRange(1, $users->lastPage()) as $page => $url)
                @if ($page == $users->currentPage())
                    <span class="active">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($users->hasMorePages())
                <a href="{{ $users->nextPageUrl() }}">Вперёд →</a>
            @else
                <span style="opacity: 0.5;">Вперёд →</span>
            @endif
        </div>
    @endif
</div>
<!-- Balance Modal -->
<div id="balanceModal" class="modal">
    <div class="modal-content">
        <h2 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: 1rem;">
            Управление балансом
        </h2>
        <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f9fafb; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: #6b7280;">Пользователь</div>
            <div style="font-weight: 600; color: #111827;" id="modalUserName"></div>
            <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">Текущий баланс</div>
            <div style="font-weight: 700; color: #10b981; font-size: 1.25rem;" id="modalCurrentBalance"></div>
        </div>

        <form id="balanceForm" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: #374151;">Действие</label>
                <select name="action" class="form-select" style="width: 100%;" required>
                    <option value="add">Пополнить баланс</option>
                    <option value="subtract">Списать с баланса</option>
                    <option value="set">Установить баланс</option>
                </select>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: #374151;">Сумма (₽)</label>
                <input type="number" name="amount" step="0.01" min="0" class="form-input" style="width: 100%;" required>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn-green" style="flex: 1;">Сохранить</button>
                <button type="button" onclick="closeBalanceModal()" style="flex: 1; background: #d1d5db; color: #374151; padding: 0.625rem 1.5rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer;">
                    Отмена
                </button>
            </div>
        </form>
    </div>
</div>

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
@endsection
