<div style="margin-bottom: var(--space-4); display: flex; gap: var(--space-3); flex-wrap: wrap;">
    <a href="{{ route('cabinet.api-keys.index') }}" class="btn btn-sm {{ request()->routeIs('cabinet.api-keys.*') ? 'btn-primary' : '' }}">Ключи</a>
    <a href="{{ route('cabinet.senders.index') }}" class="btn btn-sm {{ request()->routeIs('cabinet.senders.*') ? 'btn-primary' : '' }}">Отправители</a>
    <a href="{{ route('cabinet.api-submissions.index') }}" class="btn btn-sm {{ request()->routeIs('cabinet.api-submissions.*') ? 'btn-primary' : '' }}">Заявки</a>
    <a href="{{ route('cabinet.api-docs.guide') }}" class="btn btn-sm {{ request()->routeIs('cabinet.api-docs.guide') ? 'btn-primary' : '' }}">Документация</a>
    <a href="{{ route('cabinet.api-docs.reference') }}" class="btn btn-sm {{ request()->routeIs('cabinet.api-docs.reference') ? 'btn-primary' : '' }}">Справочник методов</a>
</div>
