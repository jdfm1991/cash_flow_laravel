<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Transacciones</title>
    <style>
        /* ============================================
           ESTILOS COMPATIBLES CON DOMPDF
           ============================================ */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 10px;
            color: #1a202c;
            background: #ffffff;
            padding: 20px;
            line-height: 1.4;
        }

        /* ========== ENCABEZADO ========== */
        .header {
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .header-title {
            font-size: 22px;
            font-weight: bold;
            color: #4f46e5;
        }

        .header-subtitle {
            font-size: 14px;
            color: #4a5568;
            margin-top: 2px;
        }

        .header-meta {
            font-size: 9px;
            color: #718096;
            margin-top: 4px;
        }

        .header-meta span {
            margin-right: 15px;
        }

        /* ========== RESUMEN ========== */
        .summary-cards {
            margin-bottom: 15px;
            border-collapse: collapse;
            width: 100%;
        }

        .summary-cards td {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            text-align: center;
            background-color: #f8fafc;
        }

        .summary-label {
            font-size: 9px;
            color: #718096;
            text-transform: uppercase;
            font-weight: bold;
        }

        .summary-value {
            font-size: 16px;
            font-weight: bold;
        }

        .summary-value.income {
            color: #10b981;
        }

        .summary-value.expense {
            color: #ef4444;
        }

        .summary-value.total {
            color: #4f46e5;
        }

        .summary-sub {
            font-size: 10px;
            color: #6b7280;
        }

        /* ========== FILTROS APLICADOS ========== */
        .filters {
            margin-bottom: 15px;
            padding: 8px 12px;
            background-color: #f3f4f6;
            border-radius: 4px;
            font-size: 9px;
            color: #4a5568;
        }

        .filters strong {
            color: #1a202c;
        }

        .filter-tag {
            display: inline-block;
            padding: 2px 8px;
            background-color: #e5e7eb;
            border-radius: 3px;
            margin-right: 5px;
        }

        /* ========== TABLA ========== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 15px;
        }

        .data-table th {
            background-color: #f3f4f6;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
        }

        .data-table td {
            padding: 4px 8px;
            border: 1px solid #d1d5db;
        }

        .data-table .text-right {
            text-align: right;
        }

        .data-table .text-center {
            text-align: center;
        }

        .data-table .income-row td {
            background-color: #f0fdf4;
        }

        .data-table .expense-row td {
            background-color: #fef2f2;
        }

        .data-table .total-row {
            background-color: #f0f4ff;
            font-weight: bold;
        }

        .data-table .text-success {
            color: #10b981;
        }

        .data-table .text-danger {
            color: #ef4444;
        }

        /* ========== PIE DE PÁGINA ========== */
        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 8px;
            color: #718096;
            text-align: center;
        }
    </style>
</head>

<body>

    <!-- ============================================ -->
    <!-- 1. ENCABEZADO DEL REPORTE                     -->
    <!-- ============================================ -->
    <div class="header">
        <div class="header-title">{{ $companyName ?? 'Empresa' }}</div>
        <div class="header-subtitle">{{ $title ?? 'Reporte de Transacciones' }}</div>
        <div class="header-meta">
            <span>📅 Período: {{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</span>
            <span>🕐 Generado: {{ $generated_at ?? now()->format('d/m/Y H:i:s') }}</span>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 2. RESUMEN DE TOTALES                        -->
    <!-- ============================================ -->
    <table class="summary-cards">
        <tr>
            <td>
                <div class="summary-label">📊 Total Movimientos</div>
                <div class="summary-value total">
                    $ {{ number_format($summary['total_original'] ?? 0, 2) }}
                </div>
                <div class="summary-sub">
                    Bs.S {{ number_format($summary['total'] ?? 0, 2) }}
                </div>
                <div class="summary-sub" style="margin-top: 2px;">
                    {{ number_format($summary['count'] ?? 0) }} transacciones
                </div>
            </td>
            <td>
                <div class="summary-label">💰 Total Ingresos</div>
                <div class="summary-value income">
                    $ {{ number_format($summary['income_original'] ?? 0, 2) }}
                </div>
                <div class="summary-sub">
                    Bs.S {{ number_format($summary['income'] ?? 0, 2) }}
                </div>
                <div class="summary-sub" style="margin-top: 2px;">
                    {{ number_format($summary['income_count'] ?? 0) }} transacciones
                </div>
            </td>
            <td>
                <div class="summary-label">💸 Total Egresos</div>
                <div class="summary-value expense">
                    $ {{ number_format($summary['expense_original'] ?? 0, 2) }}
                </div>
                <div class="summary-sub">
                    Bs.S {{ number_format($summary['expense'] ?? 0, 2) }}
                </div>
                <div class="summary-sub" style="margin-top: 2px;">
                    {{ number_format($summary['expense_count'] ?? 0) }} transacciones
                </div>
            </td>
        </tr>
    </table>

    <!-- ============================================ -->
    <!-- 3. FILTROS APLICADOS                         -->
    <!-- ============================================ -->
    <div class="filters">
        <strong>🔍 Filtros aplicados:</strong>
        @if ($filters['type'] ?? false)
            <span class="filter-tag">Tipo: {{ $filters['type'] }}</span>
        @endif
        @if ($filters['category'] ?? false)
            <span class="filter-tag">Categoría: {{ $filters['category'] }}</span>
        @endif
        @if ($filters['account'] ?? false)
            <span class="filter-tag">Cuenta: {{ $filters['account'] }}</span>
        @endif
        @if ($filters['bank_account'] ?? false)
            <span class="filter-tag">Banco: {{ $filters['bank_account'] }}</span>
        @endif
        @if (
            !($filters['type'] ?? false) &&
                !($filters['category'] ?? false) &&
                !($filters['account'] ?? false) &&
                !($filters['bank_account'] ?? false))
            <span class="filter-tag">Todos</span>
        @endif
    </div>

    {{-- Mostrar mensaje de truncamiento si aplica --}}
    @if ($truncated ?? false)
        <div
            style="padding: 8px 12px; margin-bottom: 10px; background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px; color: #92400e; font-size: 9px;">
            {{ $truncated_message ?? '⚠️ Se ha limitado el número de transacciones mostradas.' }}
        </div>
    @endif


    <!-- ============================================ -->
    <!-- 4. TABLA DE TRANSACCIONES                    -->
    <!-- ============================================ -->
    <table class="data-table" style="font-size: 8px; width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th
                    style="padding: 3px 5px; border: 1px solid #d1d5db; text-align: left; font-size: 7px; text-transform: uppercase; background-color: #f3f4f6;">
                    Fecha</th>
                <th
                    style="padding: 3px 5px; border: 1px solid #d1d5db; text-align: left; font-size: 7px; text-transform: uppercase; background-color: #f3f4f6;">
                    Tipo</th>
                <th
                    style="padding: 3px 5px; border: 1px solid #d1d5db; text-align: left; font-size: 7px; text-transform: uppercase; background-color: #f3f4f6;">
                    Descripción</th>
                <th
                    style="padding: 3px 5px; border: 1px solid #d1d5db; text-align: left; font-size: 7px; text-transform: uppercase; background-color: #f3f4f6;">
                    Categoría</th>
                <th
                    style="padding: 3px 5px; border: 1px solid #d1d5db; text-align: left; font-size: 7px; text-transform: uppercase; background-color: #f3f4f6;">
                    Cuenta</th>
                <th
                    style="padding: 3px 5px; border: 1px solid #d1d5db; text-align: right; font-size: 7px; text-transform: uppercase; background-color: #f3f4f6;">
                    Monto Original</th>
                <th
                    style="padding: 3px 5px; border: 1px solid #d1d5db; text-align: right; font-size: 7px; text-transform: uppercase; background-color: #f3f4f6;">
                    Monto Convertido</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $transaction)
                <tr
                    style="{{ $transaction['type'] === 'income' ? 'background-color: #f0fdf4;' : 'background-color: #fef2f2;' }}">
                    <td style="padding: 2px 5px; border: 1px solid #d1d5db; font-size: 8px;">
                        {{ \Carbon\Carbon::parse($transaction['date'])->format('d/m/Y') }}</td>
                    <td style="padding: 2px 5px; border: 1px solid #d1d5db; font-size: 8px;">
                        <span
                            style="padding: 1px 4px; border-radius: 2px; font-size: 7px; font-weight: bold; 
                        {{ $transaction['type'] === 'income' ? 'background-color: #d1fae5; color: #065f46;' : '' }}
                        {{ $transaction['type'] === 'expense' ? 'background-color: #fee2e2; color: #991b1b;' : '' }}">
                            {{ $transaction['type_label'] ?? $transaction['type'] }}
                        </span>
                    </td>
                    <td style="padding: 2px 5px; border: 1px solid #d1d5db; font-size: 8px;">
                        {{ Str::limit($transaction['description'] ?? '', 30) }}</td>
                    <td style="padding: 2px 5px; border: 1px solid #d1d5db; font-size: 8px;">
                        {{ $transaction['category'] ?? 'N/A' }}</td>
                    <td style="padding: 2px 5px; border: 1px solid #d1d5db; font-size: 8px;">
                        {{ Str::limit($transaction['account'] ?? 'N/A', 15) }}</td>
                    <td
                        style="padding: 2px 5px; border: 1px solid #d1d5db; text-align: right; font-size: 8px; {{ $transaction['type'] === 'income' ? 'color: #10b981;' : 'color: #ef4444;' }}">
                        {{ $transaction['currency'] ?? '' }} {{ number_format($transaction['amount'] ?? 0, 2) }}
                    </td>
                    <td style="padding: 2px 5px; border: 1px solid #d1d5db; text-align: right; font-size: 8px;">
                        Bs.S {{ number_format($transaction['amount_converted'] ?? 0, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #9ca3af; padding: 15px; font-size: 9px;">
                        No hay transacciones para mostrar en este período
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ============================================ -->
    <!-- 5. PIE DE PÁGINA                            -->
    <!-- ============================================ -->
    <div class="footer">
        <p>Reporte generado automáticamente por FlowControl {{ now()->format('d/m/Y H:i:s') }}</p>
        <p style="margin-top: 2px; font-size: 7px; color: #9ca3af;">
            Este reporte contiene información financiera confidencial. Uso interno exclusivo.
        </p>
    </div>

</body>

</html>
