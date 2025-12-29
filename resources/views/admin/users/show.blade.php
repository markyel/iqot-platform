@extends('layouts.cabinet')

@section('title', 'Пользователь: ' . $user->name)

@push('styles')
<style>
    .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; padding: 1.5rem; }
    .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    .info-item { padding: 0.75rem; background: #f9fafb; border-radius: 0.5rem; }
    .info-label { font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem; }
    .info-value { font-size: 0.875rem; color: #111827; font-weight: 500; }
    .btn { padding: 0.625rem 1.25rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-secondary { background: #6b7280; color: white; }
    .btn-secondary:hover { background: #4b5563; }
</style>
@endpush

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">

    @if(session('success'))
    <div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem;">
        <p style="color: #065f46; font-weight: 600;">{{ session('success') }}</p>
    </div>
    @endif

    <!-- Кнопка назад -->
    <div style="margin-bottom: 1.5rem;">
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">← Назад к списку</a>
    </div>

    <!-- Основная информация -->
    <div class="card">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">{{ $user->name }}</h1>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value">{{ $user->email }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Баланс</div>
                <div class="info-value">{{ number_format($user->balance, 2) }} ₽</div>
            </div>
            <div class="info-item">
                <div class="info-label">Доступный баланс</div>
                <div class="info-value">{{ number_format($user->available_balance, 2) }} ₽</div>
            </div>
            <div class="info-item">
                <div class="info-label">Компания</div>
                <div class="info-value">{{ $user->company_name ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Телефон</div>
                <div class="info-value">{{ $user->phone ?? $user->company_phone ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Контактное лицо</div>
                <div class="info-value">{{ $user->contact_person ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Sender ID</div>
                <div class="info-value">{{ $user->sender_id ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Organization ID</div>
                <div class="info-value">{{ $user->client_organization_id ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Зарегистрирован</div>
                <div class="info-value">{{ $user->created_at->format('d.m.Y H:i') }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Роль</div>
                <div class="info-value">{{ $user->is_admin ? 'Администратор' : 'Пользователь' }}</div>
            </div>
        </div>

        @if($user->sender_id)
        <div style="margin-top: 1rem;">
            <a href="{{ route('admin.users.sender.show', $user) }}" class="btn btn-secondary">
                Управление Sender
            </a>
        </div>
        @endif
    </div>

    <!-- Статистика заявок -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Статистика</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Всего заявок</div>
                <div class="info-value">{{ $user->requests->count() }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Покупок</div>
                <div class="info-value">{{ $user->purchases_count }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Заморожено средств</div>
                <div class="info-value">
                    {{ number_format($user->balanceHolds()->where('status', 'held')->sum('amount'), 2) }} ₽
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Потрачено</div>
                <div class="info-value">{{ number_format($user->purchases_sum, 2) }} ₽</div>
            </div>
        </div>
    </div>

    <!-- Последние заявки -->
    @if($user->requests->count() > 0)
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">Последние заявки</h2>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb;">
                        <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; color: #6b7280;">Номер</th>
                        <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; color: #6b7280;">Название</th>
                        <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; color: #6b7280;">Статус</th>
                        <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; color: #6b7280;">Дата</th>
                        <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; color: #6b7280;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($user->requests->take(10) as $request)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 0.75rem; font-family: monospace;">{{ $request->request_number }}</td>
                        <td style="padding: 0.75rem;">{{ $request->title }}</td>
                        <td style="padding: 0.75rem;">{{ $request->status }}</td>
                        <td style="padding: 0.75rem; color: #6b7280; font-size: 0.875rem;">
                            {{ $request->created_at->format('d.m.Y H:i') }}
                        </td>
                        <td style="padding: 0.75rem;">
                            <a href="{{ route('admin.requests.show', $request->id) }}" style="color: #3b82f6; text-decoration: none; font-weight: 500;">
                                Открыть →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
