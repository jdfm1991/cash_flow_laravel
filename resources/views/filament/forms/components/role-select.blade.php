<x-filament::fieldset
    :label="$getLabel()"
    :helper-text="$getHelperText()"
>
    <div class="space-y-2">
        {{ $getChildComponentContainer() }}
        
        @php
            $selected = $getState() ?? [];
            $options = $getOptions();
        @endphp

        <div class="mt-2 grid grid-cols-2 gap-2">
            @foreach($options as $value => $label)
                <label 
                    class="flex items-center p-2 border rounded-lg cursor-pointer transition hover:bg-gray-50 dark:hover:bg-gray-800 {{ in_array($value, $selected) ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-700' }}"
                >
                    <input
                        type="checkbox"
                        value="{{ $value }}"
                        wire:model="{{ $getStatePath() }}"
                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:checked:bg-primary-600"
                    />
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                        {{ $label }}
                    </span>
                </label>
            @endforeach
        </div>

        @if(count($options) === 0)
            <div class="text-sm text-gray-500 dark:text-gray-400">
                No hay roles disponibles.
            </div>
        @endif
    </div>
</x-filament::fieldset>