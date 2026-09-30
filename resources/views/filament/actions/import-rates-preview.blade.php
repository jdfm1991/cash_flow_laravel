<div class="space-y-4">
    @if($preview['success'])
        {{-- Resumen --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $preview['total'] }}</div>
                <div class="text-xs text-gray-600 dark:text-gray-400">Total Encontradas</div>
            </div>
            <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">
                <div class="text-2xl font-bold text-green-600">{{ $preview['new'] }}</div>
                <div class="text-xs text-gray-600 dark:text-gray-400">Nuevas a Importar</div>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <div class="text-2xl font-bold text-gray-600">{{ $preview['existing'] }}</div>
                <div class="text-xs text-gray-600 dark:text-gray-400">Ya Existentes</div>
            </div>
        </div>

        {{-- Información adicional --}}
        <div class="text-sm text-gray-600 dark:text-gray-400">
            <p><strong>Moneda base:</strong> {{ $preview['base_currency'] }}</p>
        </div>

        {{-- Preview de las primeras tasas --}}
        @if(count($preview['items']) > 0)
            <div>
                <h4 class="text-sm font-bold mb-2">Vista previa (primeras 10 tasas):</h4>
                <div class="overflow-x-auto max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                            <tr>
                                <th class="text-left px-3 py-2 border-b">Fecha</th>
                                <th class="text-left px-3 py-2 border-b">Moneda</th>
                                <th class="text-right px-3 py-2 border-b">Tasa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($preview['items'] as $item)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-1.5">{{ $item['date'] }}</td>
                                    <td class="px-3 py-1.5">
                                        <span class="font-medium">{{ $item['currency_code'] }}</span>
                                        <span class="text-gray-400 text-xs"> - {{ $item['currency_name'] }}</span>
                                    </td>
                                    <td class="text-right px-3 py-1.5 font-medium text-green-600">
                                        {{ number_format($item['rate'], 4) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($preview['new'] > 10)
                    <p class="text-xs text-gray-500 mt-2">
                        ... y {{ $preview['new'] - 10 }} tasas más.
                    </p>
                @endif
            </div>
        @else
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded text-center text-sm text-gray-500">
                No hay tasas nuevas para importar. Todas las tasas ya están registradas.
            </div>
        @endif
    @else
        <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded text-center text-sm text-red-600">
            {{ $preview['message'] }}
        </div>
    @endif
</div>