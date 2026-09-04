@php
    $data = $getState();
    $transactionId = $data['transaction_id'];
    $selected = $data['selected'];
    $options = $data['options'];
@endphp

<div x-data="{
    value: '{{ $selected }}',
    open: false,
    options: {{ json_encode($options) }},
    selectedLabel() {
        return this.options[this.value] || this.value || 'Seleccionar...';
    },
    selectOption(key) {
        this.value = key;
        this.open = false;
        @this.call('updateAccount', {{ $transactionId }}, key);
    }
}" class="relative w-full min-w-[140px] max-w-[200px]">
    <button 
        @click="open = !open" 
        @click.away="open = false"
        class="w-full flex items-center justify-between px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition min-h-[32px] gap-1"
    >
        <span x-text="selectedLabel()" class="truncate text-xs"></span>
        <svg class="w-1 h-1 flex-shrink-0 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
    
    <div 
        x-show="open"
        @click.away="open = false"
        class="absolute z-50 mt-1 w-full max-h-48 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
    >
        <div class="p-1">
            <template x-for="(label, key) in options" :key="key">
                <button 
                    @click="selectOption(key)"
                    class="w-full text-left px-3 py-1.5 text-sm rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                    :class="value === key ? 'bg-gray-100 dark:bg-gray-700' : ''"
                    x-text="label"
                ></button>
            </template>
            
            @if(empty($options))
                <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    No hay cuentas disponibles
                </div>
            @endif
        </div>
    </div>
</div>