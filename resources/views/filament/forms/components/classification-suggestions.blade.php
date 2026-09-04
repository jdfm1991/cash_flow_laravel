@php
    $record = $getRecord();
    $suggestions = $get('suggestions') ?: [];
@endphp

@if(!empty($suggestions))
    <div class="mt-2">
        <span class="text-xs text-gray-500 dark:text-gray-400">Sugerencias:</span>
        <div class="flex flex-wrap gap-1 mt-1">
            @foreach($suggestions as $suggestion)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                    {{ $suggestion }}
                </span>
            @endforeach
        </div>
    </div>
@endif