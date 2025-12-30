@extends('layouts.cabinet')

@section('title', 'Мои заявки')
@section('header', 'Мои заявки')

@push('styles')
<style>
    .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; padding: 1.5rem; }
    .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-draft { background: #f3f4f6; color: #6b7280; }
    .badge-sending { background: #dbeafe; color: #1e40af; }
    .badge-collecting { background: #e0e7ff; color: #3730a3; }
    .badge-completed { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .btn { padding: 0.625rem 1.25rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-primary:hover { background: #2563eb; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    .table th { background: #f9fafb; font-weight: 600; color: #6b7280; font-size: 0.875rem; }
    .table tbody tr:hover { background: #f9fafb; }
</style>
@endpush

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Мои заявки</h1>
            <p style="color: #6b7280;">Список ваших заявок на мониторинг позиций</p>
        </div>
        <a href="{{ route('cabinet.my.requests.create') }}" class="btn btn-primary">+ Создать заявку</a>
    </div>

    @if($requests->count() > 0)
    <div class="card" style="padding: 0; overflow: hidden;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 150px;">Номер</th>
                    <th>Название</th>
                    <th style="width: 120px;">Статус</th>
                    <th style="width: 80px; text-align: center;">Позиций</th>
                    <th style="width: 100px; text-align: right;">Стоимость</th>
                    <th style="width: 150px;">Дата создания</th>
                    <th style="width: 150px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($requests as $request)
                <tr>
                    <td>
                        <a href="{{ route('cabinet.my.requests.show', $request->id) }}" style="color: #3b82f6; text-decoration: none; font-weight: 600;">
                            {{ $request->request_number ?? $request->code }}
                        </a>
                    </td>
                    <td>{{ $request->title }}</td>
                    <td>
                        @php
                            $statusClass = match($request->status) {
                                'draft' => 'badge-draft',
                                'pending' => 'badge-pending',
                                'sending' => 'badge-sending',
                                'collecting' => 'badge-collecting',
                                'completed' => 'badge-completed',
                                'cancelled' => 'badge-cancelled',
                                default => 'badge-draft'
                            };
                            $statusText = \App\Models\Request::statuses()[$request->status] ?? $request->status;
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                    </td>
                    <td style="text-align: center;">{{ $request->items_count }}</td>
                    <td style="text-align: right; font-weight: 600; color: #6b7280;">
                        @if($request->balanceHold)
                            {{ number_format($request->balanceHold->amount, 2) }} ₽
                        @else
                            —
                        @endif
                    </td>
                    <td style="color: #6b7280; font-size: 0.875rem;">
                        {{ $request->created_at->format('d.m.Y H:i') }}
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="{{ route('cabinet.my.requests.show', $request->id) }}" style="color: #3b82f6; text-decoration: none; font-weight: 600; font-size: 0.875rem;">
                                Подробнее
                            </a>
                            @if($request->synced_to_main_db && $request->main_db_request_id)
                            <a href="{{ route('cabinet.my.requests.report', $request->id) }}" style="color: #10b981; text-decoration: none; font-weight: 600; font-size: 0.875rem;">
                                📊 Отчет
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($requests->hasPages())
    <div style="margin-top: 1.5rem;">
        {{ $requests->links() }}
    </div>
    @endif

    @else
    <div class="card" style="text-align: center; padding: 3rem;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">У вас пока нет заявок</h2>
        <p style="color: #6b7280; margin-bottom: 1.5rem;">Создайте первую заявку для мониторинга позиций</p>
        <a href="{{ route('cabinet.my.requests.create') }}" class="btn btn-primary">Создать заявку</a>
    </div>
    @endif
</div>
@endsection
