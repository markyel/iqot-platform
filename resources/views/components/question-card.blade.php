@props([
    'requestCode',
    'itemName',
    'questionText',
    'suppliers' => [],
    'suppliersCount' => null,
    'time',
    'status' => 'pending',
    'onAnswer' => null,
    'onSkip' => null
])

@php
    $badgeClass = match($status) {
        'answered' => 'badge-question-answered',
        'skipped' => 'badge-question-skipped',
        default => 'badge-question-pending'
    };

    $statusText = match($status) {
        'answered' => 'Отвечено',
        'skipped' => 'Пропущено',
        default => ($suppliersCount ? "$suppliersCount поставщика" : 'Требует ответа')
    };
@endphp

<div {{ $attributes->merge(['class' => 'question-card']) }}>
    <div class="question-header">
        <div class="question-meta">
            <span class="text-code">{{ $requestCode }}</span>
            <span class="question-separator">•</span>
            <span class="question-item">{{ $itemName }}</span>
        </div>
        <span class="badge badge-dot {{ $badgeClass }}">{{ $statusText }}</span>
    </div>

    <div class="question-body">
        <p class="question-text">{{ $questionText }}</p>

        @if(count($suppliers) > 0)
            <div class="question-suppliers">
                @foreach($suppliers as $supplier)
                    <span class="supplier-tag">{{ $supplier }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="question-footer">
        <span class="question-time">{{ $time }}</span>
        <div class="question-actions">
            @if($status === 'pending')
                @if($onSkip)
                    <button class="btn btn-ghost btn-sm" onclick="{{ $onSkip }}">
                        Пропустить
                    </button>
                @endif
                @if($onAnswer)
                    <button class="btn btn-primary btn-sm" onclick="{{ $onAnswer }}">
                        <i data-lucide="message-circle" class="icon-sm"></i>
                        Ответить
                    </button>
                @endif
            @else
                @if(isset($actions))
                    {{ $actions }}
                @endif
            @endif
        </div>
    </div>
</div>
