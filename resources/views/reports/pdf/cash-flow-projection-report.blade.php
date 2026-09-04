<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyección de Flujo de Caja</title>
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
        
        .scenario-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        
        /* ========== RESUMEN ========== */
        .summary-cards {
            margin-bottom: 15px;
            border-collapse: collapse;
            width: 100%;
        }
        
        .summary-cards td {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            text-align: center;
            background-color: #f8fafc;
        }
        
        .summary-label {
            font-size: 8px;
            color: #718096;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .summary-value {
            font-size: 14px;
            font-weight: bold;
        }
        
        .summary-value.positive {
            color: #10b981;
        }
        
        .summary-value.negative {
            color: #ef4444;
        }
        
        .summary-value.neutral {
            color: #4f46e5;
        }
        
        /* ========== ALERTAS ========== */
        .alerts-container {
            margin-bottom: 15px;
            padding: 8px 12px;
            border-radius: 4px;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        
        .alert-item {
            padding: 3px 0;
            font-size: 9px;
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
            font-size: 12px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 8px;
        }
        
        .chart-image {
            max-width: 100%;
            height: auto;
        }
        
        /* ========== TABLA ========== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 15px;
        }
        
        .data-table th {
            background-color: #f3f4f6;
            padding: 4px 6px;
            border: 1px solid #d1d5db;
            text-align: left;
            font-weight: bold;
            font-size: 7px;
            text-transform: uppercase;
        }
        
        .data-table td {
            padding: 3px 6px;
            border: 1px solid #d1d5db;
        }
        
        .data-table .text-right {
            text-align: right;
        }
        
        .data-table .text-center {
            text-align: center;
        }
        
        .data-table .text-success {
            color: #10b981;
        }
        
        .data-table .text-danger {
            color: #ef4444;
        }
        
        .data-table .text-primary {
            color: #4f46e5;
        }
        
        .data-table .total-row {
            background-color: #f0f4ff;
            font-weight: bold;
        }
        
        /* ========== MÉTRICAS ========== */
        .metrics-container {
            margin-top: 15px;
            padding: 10px 15px;
            background-color: #f3f4f6;
            border-radius: 4px;
            border: 1px solid #d1d5db;
        }
        
        .metrics-grid {
            display: table;
            width: 100%;
        }
        
        .metrics-item {
            display: table-cell;
            text-align: center;
            padding: 4px 8px;
            font-size: 8px;
        }
        
        .metrics-label {
            color: #6b7280;
            font-size: 7px;
            text-transform: uppercase;
        }
        
        .metrics-value {
            font-weight: bold;
            font-size: 11px;
        }
        
        /* ========== PIE DE PÁGINA ========== */
        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 7px;
            color: #718096;
            text-align: center;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    
    <!-- ============================================ -->
    <!-- 1. ENCABEZADO DEL REPORTE                     -->
    <!-- ============================================ -->
    <div class="header">
        <div class="header-title">{{ $companyName ?? 'Empresa' }}</div>
        <div class="header-subtitle">
            {{ $title ?? 'Proyección de Flujo de Caja' }}
            <span class="scenario-badge" style="background-color: {{ $scenario_color ?? '#4f46e5' }}; color: white; margin-left: 10px;">
                {{ $scenario_label ?? 'Realista' }}
            </span>
        </div>
        <div class="header-meta">
            <span>📅 Período: {{ $summary['period'] ?? 'N/A' }}</span>
            <span>📊 Horizonte: {{ $summary['total_months'] ?? 0 }} meses</span>
            <span>🕐 Generado: {{ $generated_at ?? now()->format('d/m/Y H:i:s') }}</span>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 2. RESUMEN EJECUTIVO                         -->
    <!-- ============================================ -->
    <table class="summary-cards">
        <tr>
            <td>
                <div class="summary-label">💰 Saldo Inicial</div>
                <div class="summary-value neutral">
                    Bs.S {{ number_format($summary['starting_balance'] ?? 0, 2) }}
                </div>
            </td>
            <td>
                <div class="summary-label">📈 Saldo Final</div>
                <div class="summary-value {{ ($summary['final_balance'] ?? 0) >= 0 ? 'positive' : 'negative' }}">
                    Bs.S {{ number_format($summary['final_balance'] ?? 0, 2) }}
                </div>
            </td>
            <td>
                <div class="summary-label">📊 Total Ingresos</div>
                <div class="summary-value positive">
                    Bs.S {{ number_format($summary['total_income'] ?? 0, 2) }}
                </div>
            </td>
            <td>
                <div class="summary-label">💸 Total Egresos</div>
                <div class="summary-value negative">
                    Bs.S {{ number_format($summary['total_expense'] ?? 0, 2) }}
                </div>
            </td>
        </tr>
    </table>

    <!-- ============================================ -->
    <!-- 3. ALERTAS                                   -->
    <!-- ============================================ -->
    @if(!empty($alerts))
        <div class="alerts-container">
            @foreach($alerts as $alert)
                <div class="alert-item {{ $alert['type'] === 'danger' ? 'alert-danger' : 'alert-warning' }}" style="padding: 4px 8px; margin-bottom: 2px; border-radius: 3px;">
                    {{ $alert['message'] }}
                </div>
            @endforeach
        </div>
    @endif

    <!-- ============================================ -->
    <!-- 4. GRÁFICO                                   -->
    <!-- ============================================ -->
    @if(isset($chart_image_path) && file_exists($chart_image_path))
        <div class="chart-container">
            <div class="chart-title">📊 Proyección de Flujo de Caja - {{ $scenario_label ?? 'Realista' }}</div>
            <img src="{{ 'data:image/png;base64,' . base64_encode(file_get_contents($chart_image_path)) }}" 
                 alt="Proyección de Flujo" 
                 class="chart-image">
        </div>
    @endif

    <!-- ============================================ -->
    <!-- 5. TABLA DE PROYECCIÓN DETALLADA             -->
    <!-- ============================================ -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 18%;">Mes</th>
                <th style="width: 20%;" class="text-right">Ingresos</th>
                <th style="width: 20%;" class="text-right">Egresos</th>
                <th style="width: 20%;" class="text-right">Flujo Neto</th>
                <th style="width: 22%;" class="text-right">Saldo Proyectado</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalIncome = 0;
                $totalExpense = 0;
                $totalNet = 0;
            @endphp
            @foreach($projection as $month)
                @php
                    $totalIncome += $month['income'];
                    $totalExpense += $month['expense'];
                    $totalNet += $month['net'];
                @endphp
                <tr>
                    <td style="font-weight: bold;">{{ $month['month_name'] }}</td>
                    <td class="text-right text-success">
                        Bs.S {{ number_format($month['income'], 2) }}
                    </td>
                    <td class="text-right text-danger">
                        Bs.S {{ number_format($month['expense'], 2) }}
                    </td>
                    <td class="text-right {{ $month['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                        Bs.S {{ number_format($month['net'], 2) }}
                    </td>
                    <td class="text-right font-bold {{ $month['balance'] >= 0 ? 'text-primary' : 'text-danger' }}">
                        Bs.S {{ number_format($month['balance'], 2) }}
                    </td>
                </tr>
            @endforeach
            <!-- Total -->
            <tr class="total-row">
                <td style="font-weight: bold;">TOTAL</td>
                <td class="text-right text-success">
                    Bs.S {{ number_format($totalIncome, 2) }}
                </td>
                <td class="text-right text-danger">
                    Bs.S {{ number_format($totalExpense, 2) }}
                </td>
                <td class="text-right {{ $totalNet >= 0 ? 'text-success' : 'text-danger' }}">
                    Bs.S {{ number_format($totalNet, 2) }}
                </td>
                <td class="text-right text-primary">
                    Bs.S {{ number_format(end($projection)['balance'] ?? 0, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- ============================================ -->
    <!-- 6. MÉTRICAS                                  -->
    <!-- ============================================ -->
    <div class="metrics-container">
        <div class="metrics-grid">
            <div class="metrics-item">
                <div class="metrics-label">📊 Días de Cobertura</div>
                <div class="metrics-value {{ ($metrics['days_of_coverage'] ?? 0) >= 30 ? 'positive' : 'negative' }}">
                    {{ $metrics['days_of_coverage'] ?? 0 }} días
                </div>
            </div>
            <div class="metrics-item">
                <div class="metrics-label">⚖️ Punto de Equilibrio</div>
                <div class="metrics-value {{ $metrics['break_even_month'] ? 'negative' : 'positive' }}">
                    {{ $metrics['break_even_month'] ?? 'Sin quiebre' }}
                </div>
            </div>
            <div class="metrics-item">
                <div class="metrics-label">📈 Tendencia de Flujo</div>
                <div class="metrics-value {{ $metrics['flow_trend_color'] ?? '' }}">
                    {{ $metrics['flow_trend'] ?? 0 }}%
                    {{ $metrics['flow_trend_label'] ?? '' }}
                </div>
            </div>
            <div class="metrics-item">
                <div class="metrics-label">💰 Saldo Promedio</div>
                <div class="metrics-value neutral">
                    Bs.S {{ number_format($metrics['avg_net'] ?? 0, 2) }}
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 7. PIE DE PÁGINA                            -->
    <!-- ============================================ -->
    <div class="footer">
        <p>Reporte generado automáticamente por FlowControl {{ now()->format('d/m/Y H:i:s') }}</p>
        <p style="margin-top: 2px; font-size: 6px; color: #9ca3af;">
            Esta proyección es una estimación basada en datos históricos. Los resultados reales pueden variar.
        </p>
    </div>

</body>
</html>