@extends('layouts.cabinet')

@section('title', 'Senders')

@section('content')
<x-page-header title="Отправители" description="Мультиотправители для работы с несколькими организациями" />

<div style="margin-bottom: var(--space-4); display: flex; gap: var(--space-3);">
    <a href="{{ route('cabinet.api-keys.index') }}" class="btn btn-sm">Ключи</a>
    <a href="{{ route('cabinet.senders.index') }}" class="btn btn-sm btn-primary">Отправители</a>
    <a href="{{ route('cabinet.api-submissions.index') }}" class="btn btn-sm">Заявки</a>
</div>

<div style="max-width: 900px;">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: var(--space-4);">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="margin-bottom: var(--space-4);">{{ session('error') }}</div>
    @endif

    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <h2 class="card-title">Добавить sender</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('cabinet.senders.store') }}">
                @csrf
                <div class="form-group" style="margin-bottom: var(--space-4);">
                    <label class="form-label">Организация заказчика (опционально)</label>
                    <select name="client_organization_id" class="input">
                        <option value="">— без организации —</option>
                        @foreach($organizations as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div style="font-size: 0.875rem; color: var(--gray-600); margin-top: var(--space-1);">
                        Sender для этой организации будет выбран автоматически при создании submission с client_organization_id.
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: var(--space-4);">
                    <label class="form-label">External sender ID (n8n)</label>
                    <input
                        type="number"
                        name="external_sender_id"
                        class="input"
                        placeholder="ID отправителя в n8n (из N8nSenderService)"
                        min="1"
                    >
                    <div style="font-size: 0.875rem; color: var(--gray-600); margin-top: var(--space-1);">
                        Legacy-поле для совместимости с текущим SMTP-пайплайном.
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: var(--space-4);">
                    <label style="display: flex; align-items: center; gap: var(--space-2);">
                        <input type="checkbox" name="is_default" value="1">
                        Сделать default
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Добавить</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Ваши senders</h2>
        </div>
        <div class="card-body">
            @if($senders->isEmpty())
                <p style="color: var(--gray-600);">Пока нет senders.</p>
            @else
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Default</th>
                            <th>Организация</th>
                            <th>External ID</th>
                            <th>Активен</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($senders as $sender)
                            <tr>
                                <td>
                                    @if($sender->is_default)
                                        <span style="color: var(--green-600); font-weight: 600;">★ default</span>
                                    @else
                                        <form method="POST" action="{{ route('cabinet.senders.default', $sender) }}" style="margin: 0;">
                                            @csrf
                                            <button type="submit" class="btn btn-sm">Сделать default</button>
                                        </form>
                                    @endif
                                </td>
                                <td>
                                    @if($sender->client_organization_id)
                                        ID {{ $sender->client_organization_id }}
                                        @if(isset($organizations[$sender->client_organization_id]))
                                            — {{ $organizations[$sender->client_organization_id] }}
                                        @endif
                                    @else
                                        <span style="color: var(--gray-500);">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($sender->external_sender_id)
                                        <code>{{ $sender->external_sender_id }}</code>
                                    @else
                                        <span style="color: var(--gray-500);">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($sender->is_active)
                                        <span style="color: var(--green-600);">да</span>
                                    @else
                                        <span style="color: var(--gray-500);">нет</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('cabinet.senders.destroy', $sender) }}"
                                          onsubmit="return confirm('Удалить sender?');"
                                          style="margin: 0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
