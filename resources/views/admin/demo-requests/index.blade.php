@extends('layouts.cabinet')

@section('title', 'Заявки на демонстрацию')

@push('styles')
<style>
    /* Dark theme for admin */
    body { background: #0f1117 !important; color: #9ca3af; }
    .sidebar { background: #161a22 !important; border-right-color: rgba(255,255,255,0.08) !important; }
    .sidebar-header { border-bottom-color: rgba(255,255,255,0.08) !important; }
    .logo { color: #10b981 !important; }
    .nav-item { color: #9ca3af !important; }
    .nav-item:hover, .nav-item.active { background: rgba(16, 185, 129, 0.1) !important; color: #10b981 !important; }
    .main-content { background: #0f1117 !important; }
    .header { background: #161a22 !important; border-bottom-color: rgba(255,255,255,0.08) !important; }
    .header h1 { color: #fff !important; }
    .header span { color: #9ca3af !important; }
    .header button { background: #1f2937 !important; color: #9ca3af !important; }
    .header button:hover { background: #374151 !important; }

    .admin-card {
        background: rgba(22, 26, 34, 0.5);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .admin-table {
        width: 100%;
        background: rgba(22, 26, 34, 0.5);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        overflow: hidden;
    }

    .admin-table thead {
        background: rgba(15, 17, 23, 0.5);
    }

    .admin-table th {
        text-align: left;
        padding: 1rem 1.5rem;
        color: #9ca3af;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .admin-table td {
        padding: 1rem 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.08);
    }

    .admin-table tr:hover {
        background: rgba(22, 26, 34, 0.3);
    }

    .status-badge {
        display: inline-block;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid;
    }

    .status-new { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3); }
    .status-processing { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border-color: rgba(245, 158, 11, 0.3); }
    .status-contacted { background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
    .status-completed { background: rgba(107, 114, 128, 0.1); color: #9ca3af; border-color: rgba(107, 114, 128, 0.3); }

    .form-select {
        background: #1f2937;
        border: 1px solid rgba(255,255,255,0.08);
        color: #fff;
        padding: 0.625rem 1rem;
        border-radius: 8px;
        outline: none;
    }

    .form-select:focus {
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

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #10b981;
        padding: 1rem 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #ef4444;
        padding: 1rem 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }
</style>
@endpush

@section('content')
<div style="max-width: 1400px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">Заявки на демонстрацию</h1>
        <p style="color: #9ca3af;">Управление входящими заявками на демонстрацию сервиса</p>
    </div>

    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert-error">
            {{ session('error') }}
        </div>
    @endif

    <!-- Фильтры -->
    <div class="admin-card">
        <form method="GET" style="display: flex; gap: 1rem; align-items: center;">
            <select name="status" class="form-select">
                <option value="">Все статусы</option>
                <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>Новые</option>
                <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>В обработке</option>
                <option value="contacted" {{ request('status') === 'contacted' ? 'selected' : '' }}>Связались</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Завершено</option>
            </select>
            <button type="submit" class="btn-green">Применить</button>
        </form>
    </div>

    <!-- Список заявок -->
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>ФИО / Организация</th>
                <th>Контакты</th>
                <th>Статус</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $request)
            <tr>
                <td style="color: #fff; font-family: monospace; font-weight: 600;">#{{ $request->id }}</td>
                <td>
                    <div style="color: #fff; font-weight: 600; margin-bottom: 0.25rem;">{{ $request->full_name }}</div>
                    <div style="color: #9ca3af; font-size: 0.875rem;">{{ $request->organization }}</div>
                    <div style="color: #6b7280; font-size: 0.75rem;">ИНН: {{ $request->inn }}</div>
                </td>
                <td>
                    <div style="color: #d1d5db; font-size: 0.875rem;">{{ $request->email }}</div>
                    <div style="color: #9ca3af; font-size: 0.875rem;">{{ $request->phone }}</div>
                </td>
                <td>
                    @php
                        $statusClass = match($request->status) {
                            'new' => 'status-new',
                            'processing' => 'status-processing',
                            'contacted' => 'status-contacted',
                            'completed' => 'status-completed',
                            default => 'status-new'
                        };
                        $statusLabel = match($request->status) {
                            'new' => 'Новая',
                            'processing' => 'В обработке',
                            'contacted' => 'Связались',
                            'completed' => 'Завершено',
                            default => $request->status
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </td>
                <td style="color: #9ca3af; font-size: 0.875rem;">
                    {{ $request->created_at->format('d.m.Y H:i') }}
                </td>
                <td>
                    <a href="{{ route('admin.demo-requests.show', $request) }}" class="btn-green" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none;">
                        Подробнее
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: 3rem; color: #9ca3af;">
                    Заявок не найдено
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Пагинация -->
    @if($requests->hasPages())
        <div style="margin-top: 1.5rem;">
            {{ $requests->links() }}
        </div>
    @endif
</div>
@endsection
