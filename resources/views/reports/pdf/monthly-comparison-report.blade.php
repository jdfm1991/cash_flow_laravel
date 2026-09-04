<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparativo Mensual</title>
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

        .summary-value.net {
            color: #4f46e5;
        }

        .summary-sub {
            font-size: 10px;
            color: #6b7280;
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
        <div class="header-subtitle">{{ $title ?? 'Reporte Comparativo Mensual' }}</div>
        <div class="header-meta">
            <span>Año: {{ $year ?? date('Y') }}</span>
            <span>Meses: {{ count($months ?? []) }}</span>
            <span>Generado: {{ $generated_at ?? now()->format('d/m/Y H:i:s') }}</span>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 2. RESUMEN DE TOTALES                        -->
    <!-- ============================================ -->
    <table class="summary-cards">
        <tr>
            <td>
                <div class="summary-label">Total Ingresos</div>
                <div class="summary-value income">
                    VES {{ number_format($totals['income_original'] ?? 0, 2) }}
                </div>
                <div class="summary-sub">
                    USD {{ number_format($totals['income'] ?? 0, 2) }}
                </div>
            </td>
            <td>
                <div class="summary-label">Total Egresos</div>
                <div class="summary-value expense">
                    VES {{ number_format($totals['expense_original'] ?? 0, 2) }}
                </div>
                <div class="summary-sub">
                    USD {{ number_format($totals['expense'] ?? 0, 2) }}
                </div>
            </td>
            <td>
                <div class="summary-label">Balance Neto</div>
                <div
                    class="summary-value net {{ ($totals['net_original'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    VES {{ number_format($totals['net_original'] ?? 0, 2) }}
                </div>
                <div class="summary-sub">
                    USD {{ number_format($totals['net'] ?? 0, 2) }}
                </div>
            </td>
        </tr>
    </table>

    <!-- ============================================ -->
    <!-- 3. GRÁFICO DE COMPARATIVA                     -->
    <!-- ============================================ -->
    @if (isset($chart_image_path) && file_exists($chart_image_path))
        <div class="chart-container">
            <div class="chart-title">📊 Comparativa Mensual</div>
            <img src="{{ 'data:image/png;base64,' . base64_encode(file_get_contents($chart_image_path)) }}"
                alt="Comparativa Mensual" class="chart-image">
        </div>
    @endif

    <!-- ============================================ -->
    <!-- 4. TABLA DE COMPARATIVA MENSUAL              -->
    <!-- ============================================ -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20%;">Mes</th>
                <th style="width: 20%;" class="text-right">Ingresos (VES)</th>
                <th style="width: 20%;" class="text-right">Ingresos (USD)</th>
                <th style="width: 20%;" class="text-right">Egresos (VES)</th>
                <th style="width: 20%;" class="text-right">Egresos (USD)</th>
                <th style="width: 20%;" class="text-right">Balance (USD)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalIncomeOriginal = 0;
                $totalIncome = 0;
                $totalExpenseOriginal = 0;
                $totalExpense = 0;
                $totalNet = 0;
            @endphp
            @foreach ($months as $month)
                @php
                    $incomeOriginal = $month['income_original'] ?? 0;
                    $income = $month['income'] ?? 0;
                    $expenseOriginal = $month['expense_original'] ?? 0;
                    $expense = $month['expense'] ?? 0;
                    $net = $month['net'] ?? 0;

                    $totalIncomeOriginal += $incomeOriginal;
                    $totalIncome += $income;
                    $totalExpenseOriginal += $expenseOriginal;
                    $totalExpense += $expense;
                    $totalNet += $net;
                @endphp
                <tr>
                    <td style="font-weight: bold;">{{ $month['month_name'] }}</td>
                    <td class="text-right text-success">
                        VES {{ number_format($incomeOriginal, 2) }}
                    </td>
                    <td class="text-right text-success">
                        USD {{ number_format($income, 2) }}
                    </td>
                    <td class="text-right text-danger">
                        VES {{ number_format($expenseOriginal, 2) }}
                    </td>
                    <td class="text-right text-danger">
                        USD {{ number_format($expense, 2) }}
                    </td>
                    <td class="text-right font-bold {{ $net >= 0 ? 'text-success' : 'text-danger' }}">
                        USD {{ number_format($net, 2) }}
                    </td>
                </tr>
            @endforeach
            <!-- Total -->
            <tr class="total-row">
                <td style="font-weight: bold; background-color: #f0f4ff;">TOTAL</td>
                <td class="text-right text-success" style="background-color: #f0f4ff;">
                    VES {{ number_format($totalIncomeOriginal, 2) }}
                </td>
                <td class="text-right text-success" style="background-color: #f0f4ff;">
                    USD {{ number_format($totalIncome, 2) }}
                </td>
                <td class="text-right text-danger" style="background-color: #f0f4ff;">
                    VES {{ number_format($totalExpenseOriginal, 2) }}
                </td>
                <td class="text-right text-danger" style="background-color: #f0f4ff;">
                    USD {{ number_format($totalExpense, 2) }}
                </td>
                <td class="text-right font-bold {{ $totalNet >= 0 ? 'text-success' : 'text-danger' }}"
                    style="background-color: #f0f4ff;">
                    USD {{ number_format($totalNet, 2) }}
                </td>
            </tr>
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
