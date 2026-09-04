<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flujo de Caja - Resumen por Mes</title>
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
            margin-bottom: 10px;
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

        /* ========== TARJETA DE RESUMEN ========== */
        .summary-cards {
            margin-bottom: 10px;
            border-collapse: collapse;
            width: 100%;
        }

        .summary-cards td {
            padding: 4px 6px;
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
            font-size: 14px;
            font-weight: bold;
        }

        .summary-value.income {
            color: #10b981;
        }

        .summary-value.expense {
            color: #ef4444;
        }

        .summary-value.positive {
            color: #10b981;
        }

        .summary-value.negative {
            color: #ef4444;
        }

        .summary-sub {
            font-size: 10px;
            color: #6b7280;
        }

        /* ========== GRÁFICO ========== */
        .chart-container {
            text-align: center;
            margin: 15px 0 20px 0;
            padding: 15px;
            border: 1px solid #e2e8f0;
            background-color: #fafafa;
        }

        .chart-title {
            font-size: 13px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 10px;
        }

        .chart-image {
            max-width: 100%;
            height: auto;
        }

        /* ========== SECCIÓN DE MES ========== */
        .month-section {
            background: #f8fafc;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            page-break-inside: avoid;
        }

        .month-header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .month-title {
            font-size: 14px;
            font-weight: bold;
            color: #4f46e5;
        }

        .month-totals {
            font-size: 11px;
            text-align: right;
        }

        .month-totals .income-text {
            color: #10b981;
        }

        .month-totals .expense-text {
            color: #ef4444;
        }

        .month-totals .net-positive {
            color: #10b981;
        }

        .month-totals .net-negative {
            color: #ef4444;
        }

        /* ========== TABLAS DE DATOS ========== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-bottom: 6px;
        }

        .data-table th {
            background-color: #f3f4f6;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }

        .data-table td {
            padding: 5px 10px;
            border: 1px solid #d1d5db;
        }

        .data-table .text-right {
            text-align: right;
        }

        .data-table .text-center {
            text-align: center;
        }

        .data-table .income-row {
            background-color: #f0fdf4;
        }

        .data-table .expense-row {
            background-color: #fef2f2;
        }

        .data-table .subtotal-row {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .data-table .category-row td {
            font-weight: bold;
            background-color: #f8fafc;
        }

        .data-table .account-row td {
            padding-left: 20px;
        }

        .data-table .empty-row td {
            color: #9ca3af;
            font-style: italic;
            text-align: center;
            padding: 6px;
        }

        /* ========== TOTAL GENERAL ========== */
        .grand-total {
            background: #f3f4f6;
            padding: 10px 15px;
            border: 2px solid #4f46e5;
            margin-top: 15px;
            font-weight: bold;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .grand-total .income-text {
            color: #10b981;
        }

        .grand-total .expense-text {
            color: #ef4444;
        }

        .grand-total .net-positive {
            color: #10b981;
        }

        .grand-total .net-negative {
            color: #ef4444;
        }

        .grand-total .text-right {
            text-align: right;
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

        /* ========== UTILIDADES ========== */
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        .page-break {
            page-break-after: always;
        }

        .font-bold {
            font-weight: bold;
        }

        .text-xs {
            font-size: 8px;
        }

        .text-sm {
            font-size: 9px;
        }

        .text-success {
            color: #10b981;
        }

        .text-danger {
            color: #ef4444;
        }

        .text-gray {
            color: #6b7280;
        }

        .mt-1 {
            margin-top: 4px;
        }

        .mb-1 {
            margin-bottom: 4px;
        }
    </style>
</head>

<body>

    <!-- ============================================ -->
    <!-- 1. ENCABEZADO DEL REPORTE                     -->
    <!-- ============================================ -->
    <div class="header">
        <div class="header-title">{{ $companyName ?? 'Empresa' }}</div>
        <div class="header-subtitle">{{ $title ?? 'Reporte de Flujo de Caja - Resumen por Mes' }}</div>
        <div class="header-meta">
            <span>Período: {{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}</span>
            <span>Días: {{ $period['days'] ?? 0 }}</span>
            <span>Generado: {{ $generated_at ?? now()->format('d/m/Y H:i:s') }}</span>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 2. RESUMEN FINANCIERO                         -->
    <!-- ============================================ -->
    <table class="summary-cards">
        <tr>
            <td style="background-color: #f0fdf4;">
                <div class="summary-label"> Total Ingresos</div>
                <div class="summary-value income">
                    {{ $base_currency ?? 'VES' }} {{ number_format($totals['income_original'] ?? 0, 2) }}
                    <br>
                    <span class="summary-sub">({{ $base_currency ?? 'USD' }}
                        {{ number_format($totals['income_converted'] ?? 0, 2) }})</span>
                </div>
            </td>
            <td style="background-color: #fef2f2;">
                <div class="summary-label">Total Egresos</div>
                <div class="summary-value expense">
                    {{ $base_currency ?? 'VES' }} {{ number_format($totals['expense_original'] ?? 0, 2) }}
                    <br>
                    <span class="summary-sub">({{ $base_currency ?? 'USD' }}
                        {{ number_format($totals['expense_converted'] ?? 0, 2) }})</span>
                </div>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f0f4ff;">
                <div class="summary-label">Balance Neto</div>
                <div class="summary-value {{ ($totals['net_original'] ?? 0) >= 0 ? 'positive' : 'negative' }}">
                    {{ $base_currency ?? 'VES' }} {{ number_format($totals['net_original'] ?? 0, 2) }}
                    <br>
                    <span class="summary-sub">({{ $base_currency ?? 'USD' }}
                        {{ number_format($totals['net_converted'] ?? 0, 2) }})</span>
                </div>
            </td>
            <td>
                <div class="summary-label">Transacciones</div>
                <div class="summary-value">{{ $totals['transaction_count'] ?? 0 }}</div>
            </td>
        </tr>
    </table>

    <!-- ============================================ -->
    <!-- 3. GRÁFICO DE EVOLUCIÓN                      -->
    <!-- ============================================ -->
    @if (isset($chart_image_path) && file_exists($chart_image_path))
        <div class="chart-container">
            <div class="chart-title">Evolucion Mensual de Ingresos vs Egresos</div>
            <img src="{{ 'data:image/png;base64,' . base64_encode(file_get_contents($chart_image_path)) }}"
                alt="Evolucion Mensual" class="chart-image">
        </div>
    @else
        <div class="chart-container">
            <div class="chart-title"> Evolucion Mensual</div>
            <p style="color: #9ca3af; font-size: 11px;">No hay datos suficientes para generar el gráfico</p>
        </div>
    @endif


    <!-- ============================================ -->
    <!-- 4. TABLA RESUMEN MENSUAL                      -->
    <!-- ============================================ -->
    <div style="margin: 15px 0 20px 0; padding: 10px; border: 1px solid #e2e8f0; background-color: #fafafa;">
        <div style="font-weight: bold; font-size: 13px; color: #1a202c; margin-bottom: 10px; text-align: center;">
            Resumen Mensual de Ingresos y Egresos
        </div>
        <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <thead>
                <tr>
                    <th
                        style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: left;">
                        Mes</th>
                    <th
                        style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: right;">
                        Ingresos ({{ $base_currency ?? 'VES' }})</th>
                    <th
                        style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: right;">
                        Ingresos ({{ $base_currency ?? 'USD' }})</th>
                    <th
                        style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: right;">
                        Egresos ({{ $base_currency ?? 'VES' }})</th>
                    <th
                        style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: right;">
                        Egresos ({{ $base_currency ?? 'USD' }})</th>
                    <th
                        style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: right;">
                        Balance ({{ $base_currency ?? 'USD' }})</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalIncomeOriginal = 0;
                    $totalIncomeConverted = 0;
                    $totalExpenseOriginal = 0;
                    $totalExpenseConverted = 0;
                    $totalNetConverted = 0;
                @endphp
                @foreach ($monthly_summary as $month)
                    @php
                        // ✅ Usar los campos correctos
                        $incomeOriginal = $month['totals']['income_original'] ?? 0;
                        $incomeConverted = $month['totals']['income'] ?? 0;
                        $expenseOriginal = $month['totals']['expense_original'] ?? 0;
                        $expenseConverted = $month['totals']['expense'] ?? 0;
                        $netConverted = $month['totals']['net'] ?? 0;

                        $totalIncomeOriginal += $incomeOriginal;
                        $totalIncomeConverted += $incomeConverted;
                        $totalExpenseOriginal += $expenseOriginal;
                        $totalExpenseConverted += $expenseConverted;
                        $totalNetConverted += $netConverted;
                    @endphp
                    <tr>
                        <td style="padding: 3px 8px; border: 1px solid #d1d5db; font-weight: bold;">
                            {{ $month['month_name'] }}</td>
                        <td style="padding: 3px 8px; border: 1px solid #d1d5db; text-align: right; color: #10b981;">
                            {{ number_format($incomeOriginal, 2) }}
                        </td>
                        <td style="padding: 3px 8px; border: 1px solid #d1d5db; text-align: right; color: #10b981;">
                            {{ number_format($incomeConverted, 2) }}
                        </td>
                        <td style="padding: 3px 8px; border: 1px solid #d1d5db; text-align: right; color: #ef4444;">
                            {{ number_format($expenseOriginal, 2) }}
                        </td>
                        <td style="padding: 3px 8px; border: 1px solid #d1d5db; text-align: right; color: #ef4444;">
                            {{ number_format($expenseConverted, 2) }}
                        </td>
                        <td
                            style="padding: 3px 8px; border: 1px solid #d1d5db; text-align: right; font-weight: bold; color: {{ $netConverted >= 0 ? '#10b981' : '#ef4444' }};">
                            {{ number_format($netConverted, 2) }}
                        </td>
                    </tr>
                @endforeach
                <!-- Total General -->
                <tr style="background-color: #f0f4ff; font-weight: bold; border-top: 2px solid #4f46e5;">
                    <td
                        style="padding: 4px 8px; border: 1px solid #d1d5db; font-weight: bold; background-color: #f0f4ff;">
                        TOTAL</td>
                    <td
                        style="padding: 4px 8px; border: 1px solid #d1d5db; text-align: right; color: #10b981; background-color: #f0f4ff;">
                        {{ number_format($totalIncomeOriginal, 2) }}
                    </td>
                    <td
                        style="padding: 4px 8px; border: 1px solid #d1d5db; text-align: right; color: #10b981; background-color: #f0f4ff;">
                        {{ number_format($totalIncomeConverted, 2) }}
                    </td>
                    <td
                        style="padding: 4px 8px; border: 1px solid #d1d5db; text-align: right; color: #ef4444; background-color: #f0f4ff;">
                        {{ number_format($totalExpenseOriginal, 2) }}
                    </td>
                    <td
                        style="padding: 4px 8px; border: 1px solid #d1d5db; text-align: right; color: #ef4444; background-color: #f0f4ff;">
                        {{ number_format($totalExpenseConverted, 2) }}
                    </td>
                    <td
                        style="padding: 4px 8px; border: 1px solid #d1d5db; text-align: right; font-weight: bold; color: {{ $totalNetConverted >= 0 ? '#10b981' : '#ef4444' }}; background-color: #f0f4ff;">
                        {{ number_format($totalNetConverted, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ============================================ -->
    <!-- 5. GRÁFICO DE BARRAS ADICIONAL               -->
    <!-- ============================================ -->
    @if (isset($bar_chart_image_path) && file_exists($bar_chart_image_path))
        <div class="chart-container" style="margin-top: 15px;">
            <div class="chart-title"> Comparativa Mensual</div>
            <img src="{{ 'data:image/png;base64,' . base64_encode(file_get_contents($bar_chart_image_path)) }}"
                alt="Comparativa Mensual" class="chart-image">
            <p style="margin-top: 5px; font-size: 8px; color: #9ca3af; font-style: italic;">
                * Las barras verdes representan ingresos, las barras rojas representan egresos
            </p>
        </div>
    @endif

    <!-- ============================================ -->
    <!-- 5. RESUMEN POR MES                           -->
    <!-- ============================================ -->
    @foreach ($monthly_summary as $monthIndex => $month)
        <div class="month-section">
            <div class="month-header">
                <span class="month-title">{{ $month['month_name'] }}</span>
                <span class="month-totals">
                    <span class="income-text">{{ number_format($month['totals']['income'] ?? 0, 2) }}</span>
                    |
                    <span class="expense-text"> {{ number_format($month['totals']['expense'] ?? 0, 2) }}</span>
                    |
                    <span class="{{ ($month['totals']['net'] ?? 0) >= 0 ? 'net-positive' : 'net-negative' }}">
                        Balance: {{ number_format($month['totals']['net'] ?? 0, 2) }}
                    </span>
                </span>
            </div>

            <!-- ===== TABLA DE INGRESOS ===== -->
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Categoría / Cuenta</th>
                        <th style="width: 30%;" class="text-right">Monto ({{ $base_currency ?? 'VES' }})</th>
                        <th style="width: 30%;" class="text-right">Monto ({{ $base_currency ?? 'USD' }}) </th>
                    </tr>
                </thead>
                <tbody>
                    <!-- INGRESOS -->
                    <tr class="category-row income-row">
                        <td colspan="3"
                            style="font-weight: bold; color: #10b981; text-align: center; background-color: #f0fdf4;">
                            INGRESOS
                        </td>
                    </tr>

                    @forelse($month['income'] ?? [] as $category)
                        <tr class="category-row">
                            <td><strong>{{ $category['category'] }}</strong></td>
                            <td class="text-right text-success">
                                {{ $base_currency ?? 'VES' }} {{ number_format($category['total_original'] ?? 0, 2) }}
                            </td>
                            <td class="text-right text-success">
                                {{ $base_currency ?? 'USD' }}
                                {{ number_format($category['total'] ?? 0, 2) }}
                            </td>
                        </tr>
                        @foreach ($category['accounts'] ?? [] as $account)
                            <tr class="account-row">
                                <td style="padding-left: 20px;"> {{ $account['account'] }}</td>
                                <td class="text-right text-success">
                                    {{ $base_currency ?? 'VES' }}
                                    {{ number_format($account['total_original'] ?? 0, 2) }}
                                </td>
                                <td class="text-right text-success">
                                    {{ $base_currency ?? 'USD' }} {{ number_format($account['total'] ?? 0, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr class="empty-row">
                            <td colspan="3">Sin ingresos registrados en este período</td>
                        </tr>
                    @endforelse

                    <tr class="subtotal-row income-row">
                        <td><strong>Total Ingresos {{ $month['month_name'] }}</strong></td>
                        <td class="text-right text-success">
                            <strong>{{ $base_currency ?? 'VES' }}
                                {{ number_format($month['totals']['income_original'] ?? 0, 2) }}</strong>
                        </td>
                        <td class="text-right text-success">
                            <strong>{{ $base_currency ?? 'USD' }}
                                {{ number_format($month['totals']['income'] ?? 0, 2) }}</strong>
                        </td>
                    </tr>

                    <!-- EGRESOS -->
                    <tr class="category-row expense-row">
                        <td colspan="3"
                            style="font-weight: bold; color: #ef4444; text-align: center; background-color: #fef2f2;">
                            EGRESOS
                        </td>
                    </tr>

                    @forelse($month['expense'] ?? [] as $category)
                        <tr class="category-row">
                            <td><strong>{{ $category['category'] }}</strong></td>
                            <td class="text-right text-danger">
                                {{ $base_currency ?? 'VES' }} {{ number_format($category['total_original'] ?? 0, 2) }}
                            </td>
                            <td class="text-right text-danger">
                                {{ $base_currency ?? 'USD' }}
                                {{ number_format($category['total'] ?? 0, 2) }}
                            </td>
                        </tr>
                        @foreach ($category['accounts'] ?? [] as $account)
                            <tr class="account-row">
                                <td style="padding-left: 20px;"> {{ $account['account'] }}</td>
                                <td class="text-right text-danger">
                                    {{ $base_currency ?? 'VES' }}
                                    {{ number_format($account['total_original'] ?? 0, 2) }}
                                </td>
                                <td class="text-right text-danger">
                                    {{ $base_currency ?? 'USD' }} {{ number_format($account['total'] ?? 0, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr class="empty-row">
                            <td colspan="3">Sin egresos registrados en este período</td>
                        </tr>
                    @endforelse

                    <tr class="subtotal-row expense-row">
                        <td><strong>Total Egresos {{ $month['month_name'] }}</strong></td>
                        <td class="text-right text-danger">
                            <strong>{{ $base_currency ?? 'VES' }}
                                {{ number_format($month['totals']['expense_original'] ?? 0, 2) }}</strong>
                        </td>
                        <td class="text-right text-danger">
                            <strong>{{ $base_currency ?? 'USD' }}
                                {{ number_format($month['totals']['expense'] ?? 0, 2) }}</strong>
                        </td>
                    </tr>

                    <!-- TOTAL DEL MES -->
                    <tr style="background-color: #f0f4ff; font-weight: bold; border-top: 2px solid #4f46e5;">
                        <td><strong>Balance del Mes</strong></td>
                        <td
                            class="text-right {{ ($month['totals']['net_original'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                            <strong>{{ number_format($month['totals']['net_original'] ?? 0, 2) }}</strong>
                        </td>
                        <td
                            class="text-right {{ ($month['totals']['net'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                            <strong>{{ number_format($month['totals']['net'] ?? 0, 2) }}</strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach

    <!-- ============================================ -->
    <!-- 6. TOTAL GENERAL                             -->
    <!-- ============================================ -->
    <div class="grand-total">
        <span>TOTAL GENERAL</span>
        <div style="text-align: right;">
            <span class="income-text">
                Ingresos: {{ $base_currency ?? 'VES' }} {{ number_format($totals['income_original'] ?? 0, 2) }}
                <span class="text-gray text-xs">({{ $base_currency ?? 'USD' }}
                    {{ number_format($totals['income_converted'] ?? 0, 2) }})</span>
            </span>
            <span style="margin: 0 8px;">|</span>
            <span class="expense-text">
                Egresos: {{ $base_currency ?? 'VES' }} {{ number_format($totals['expense_original'] ?? 0, 2) }}
                <span class="text-gray text-xs">({{ $base_currency ?? 'USD' }}
                    {{ number_format($totals['expense_converted'] ?? 0, 2) }})</span>
            </span>
            <span style="margin: 0 8px;">|</span>
            <span class="{{ ($totals['net_original'] ?? 0) >= 0 ? 'net-positive' : 'net-negative' }}">
                Balance: {{ $base_currency ?? 'VES' }} {{ number_format($totals['net_original'] ?? 0, 2) }}
                <span class="text-gray text-xs">({{ $base_currency ?? 'USD' }}
                    {{ number_format($totals['net_converted'] ?? 0, 2) }})</span>
            </span>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 7. RESUMEN DE SALDOS BANCARIOS                -->
    <!-- ============================================ -->
    @if (!empty($bank_account_summary))
        <div
            style="margin-top: 20px; padding: 10px; border: 1px solid #e2e8f0; background-color: #fafafa; page-break-inside: avoid;">
            <div style="font-weight: bold; font-size: 13px; color: #1a202c; margin-bottom: 10px; text-align: center;">
                🏦 Resumen de Saldos Bancarios
            </div>
            <div style="font-size: 8px; color: #718096; text-align: center; margin-bottom: 8px;">
                Período: {{ $period['start'] ?? 'N/A' }} - {{ $period['end'] ?? 'N/A' }}
            </div>
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 9px;">
                <thead>
                    <tr>
                        <th
                            style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: left;">
                            Cuenta</th>
                        <th
                            style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: left;">
                            Banco</th>
                        <th
                            style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: right;">
                            Saldo Inicial</th>
                        <th
                            style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: right;">
                            Ingresos</th>
                        <th
                            style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: right;">
                            Egresos</th>
                        <th
                            style="background-color: #f3f4f6; padding: 4px 8px; border: 1px solid #d1d5db; text-align: right;">
                            Saldo Final</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalInitial = 0;
                        $totalIncome = 0;
                        $totalExpense = 0;
                        $totalFinal = 0;
                    @endphp
                    @foreach ($bank_account_summary as $account)
                        @php
                            $totalInitial += $account['initial_balance'] ?? 0;
                            $totalIncome += $account['income'] ?? 0;
                            $totalExpense += $account['expense'] ?? 0;
                            $totalFinal += $account['final_balance'] ?? 0;
                        @endphp
                        <tr>
                            <td style="padding: 3px 8px; border: 1px solid #d1d5db; font-weight: bold;">
                                {{ $account['account_alias'] ?? 'N/A' }}
                                <span style="font-weight: normal; color: #6b7280; font-size: 8px;">
                                    ({{ $account['account_number'] ?? '' }})
                                </span>
                            </td>
                            <td style="padding: 3px 8px; border: 1px solid #d1d5db;">
                                {{ $account['bank_name'] ?? 'N/A' }}
                            </td>
                            <td style="padding: 3px 8px; border: 1px solid #d1d5db; text-align: right;">
                                {{ $account['currency'] ?? '' }}
                                {{ number_format($account['initial_balance'] ?? 0, 2) }}
                            </td>
                            <td
                                style="padding: 3px 8px; border: 1px solid #d1d5db; text-align: right; color: #10b981;">
                                {{ $account['currency'] ?? '' }} {{ number_format($account['income'] ?? 0, 2) }}
                            </td>
                            <td
                                style="padding: 3px 8px; border: 1px solid #d1d5db; text-align: right; color: #ef4444;">
                                {{ $account['currency'] ?? '' }} {{ number_format($account['expense'] ?? 0, 2) }}
                            </td>
                            <td
                                style="padding: 3px 8px; border: 1px solid #d1d5db; text-align: right; font-weight: bold; color: {{ ($account['final_balance'] ?? 0) >= 0 ? '#10b981' : '#ef4444' }};">
                                {{ $account['currency'] ?? '' }}
                                {{ number_format($account['final_balance'] ?? 0, 2) }}
                            </td>
                        </tr>
                    @endforeach
                    <!-- Totales -->
                    <tr style="background-color: #f0f4ff; font-weight: bold; border-top: 2px solid #4f46e5;">
                        <td style="padding: 4px 8px; border: 1px solid #d1d5db; background-color: #f0f4ff;"
                            colspan="2">
                            <strong>TOTAL GENERAL</strong>
                        </td>
                        <td
                            style="padding: 4px 8px; border: 1px solid #d1d5db; text-align: right; background-color: #f0f4ff;">
                            {{ number_format($totalInitial, 2) }}
                        </td>
                        <td
                            style="padding: 4px 8px; border: 1px solid #d1d5db; text-align: right; color: #10b981; background-color: #f0f4ff;">
                            {{ number_format($totalIncome, 2) }}
                        </td>
                        <td
                            style="padding: 4px 8px; border: 1px solid #d1d5db; text-align: right; color: #ef4444; background-color: #f0f4ff;">
                            {{ number_format($totalExpense, 2) }}
                        </td>
                        <td
                            style="padding: 4px 8px; border: 1px solid #d1d5db; text-align: right; font-weight: bold; color: {{ $totalFinal >= 0 ? '#10b981' : '#ef4444' }}; background-color: #f0f4ff;">
                            {{ number_format($totalFinal, 2) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div style="margin-top: 5px; font-size: 7px; color: #9ca3af; text-align: right;">
                * Saldos en moneda original de cada cuenta
            </div>
        </div>
    @endif

    <!-- ============================================ -->
    <!-- 8. PIE DE PÁGINA                            -->
    <!-- ============================================ -->
    <div class="footer">
        <p>Reporte generado automáticamente por FlowControl {{ now()->format('d/m/Y H:i:s') }}</p>
        <p style="margin-top: 2px; font-size: 7px; color: #9ca3af;">
            Este reporte contiene información financiera confidencial. Uso interno exclusivo.
        </p>
    </div>

</body>

</html>
