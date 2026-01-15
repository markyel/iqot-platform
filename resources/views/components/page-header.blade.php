@props([
    'title',
    'description' => null,
    'breadcrumbs' => []
])

<div class="page-header">
    <div class="page-header-content">
        @if(count($breadcrumbs) > 0)
            <div class="page-header-breadcrumb">
                @foreach($breadcrumbs as $index => $crumb)
                    @if($index > 0)
                        <span>/</span>
                    @endif
                    @if(isset($crumb['url']))
                        <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                    @else
                        <span>{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </div>
        @endif
        <h1 class="page-header-title">{{ $title }}</h1>
        @if($description)
            <p class="page-header-description">{{ $description }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="page-header-actions">
            {{ $actions }}
        </div>
    @endif
</div>
