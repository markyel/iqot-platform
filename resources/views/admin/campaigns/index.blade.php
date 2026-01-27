@extends('layouts.cabinet')

@section('title', 'Email рассылки')

@section('content')
<x-page-header
    title="Email рассылки"
    description="Управление email кампаниями и инвайтами"
>
    <x-slot name="actions">
        <x-button
            variant="accent"
            icon="plus"
            href="{{ route('admin.campaigns.create') }}"
        >
            Создать рассылку
        </x-button>
    </x-slot>
</x-page-header>

<div class="card">
    @if($campaigns->isEmpty())
        <div class="empty-state">
            <i data-lucide="mail" class="empty-state-icon"></i>
            <h3 class="empty-state-title">Нет рассылок</h3>
            <p class="empty-state-description">
                Создайте первую email рассылку для отправки промокодов или другой информации
            </p>
        </div>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Тема письма</th>
                    <th>Получателей</th>
                    <th>Отправлено</th>
                    <th>Ошибки</th>
                    <th>Статус</th>
                    <th>Дата создания</th>
                    <th class="text-right">Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($campaigns as $campaign)
                <tr>
                    <td>
                        <a href="{{ route('admin.campaigns.show', $campaign) }}" class="link">
                            {{ $campaign->name }}
                        </a>
                    </td>
                    <td>{{ $campaign->subject }}</td>
                    <td>{{ $campaign->total_recipients }}</td>
                    <td>
                        <span class="text-success">{{ $campaign->sent_count }}</span>
                    </td>
                    <td>
                        @if($campaign->failed_count > 0)
                            <span class="text-danger">{{ $campaign->failed_count }}</span>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                    <td>
                        @if($campaign->status === 'draft')
                            <x-badge type="pending">Черновик</x-badge>
                        @elseif($campaign->status === 'sending')
                            <x-badge type="active">Отправка</x-badge>
                        @elseif($campaign->status === 'completed')
                            <x-badge type="completed">Завершена</x-badge>
                        @else
                            <x-badge type="failed">Ошибка</x-badge>
                        @endif
                    </td>
                    <td>{{ $campaign->created_at->format('d.m.Y H:i') }}</td>
                    <td class="text-right">
                        <x-button
                            variant="secondary"
                            size="sm"
                            href="{{ route('admin.campaigns.show', $campaign) }}"
                        >
                            Просмотр
                        </x-button>
                        @if($campaign->isEditable())
                            <form action="{{ route('admin.campaigns.destroy', $campaign) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <x-button
                                    type="submit"
                                    variant="danger"
                                    size="sm"
                                    onclick="return confirm('Удалить рассылку?')"
                                >
                                    Удалить
                                </x-button>
                            </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="card-footer">
            {{ $campaigns->links() }}
        </div>
    @endif
</div>
@endsection
