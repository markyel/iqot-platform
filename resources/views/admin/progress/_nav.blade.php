<div style="margin-bottom: var(--space-5); display: flex; gap: var(--space-2); flex-wrap: wrap;">
    <a href="{{ route('admin.emails.campaigns') }}" class="btn btn-sm {{ request()->routeIs('admin.emails.campaigns', 'admin.progress.request') ? 'btn-primary' : '' }}">Заявки</a>
    <a href="{{ route('admin.progress.api') }}" class="btn btn-sm {{ request()->routeIs('admin.progress.api') ? 'btn-primary' : '' }}">API-заявки</a>
    <a href="{{ route('admin.progress.monitoring') }}" class="btn btn-sm {{ request()->routeIs('admin.progress.monitoring') ? 'btn-primary' : '' }}">Мониторинг цен</a>
    <a href="{{ route('admin.progress.coverage') }}" class="btn btn-sm {{ request()->routeIs('admin.progress.coverage') ? 'btn-primary' : '' }}">Охват поставщиков</a>
    <a href="{{ route('admin.progress.resources') }}" class="btn btn-sm {{ request()->routeIs('admin.progress.resources') ? 'btn-primary' : '' }}">Ресурсы системы</a>
</div>
