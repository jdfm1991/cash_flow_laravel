<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default PDF Renderer
    |--------------------------------------------------------------------------
    |
    | The default renderer to use for generating PDFs.
    | Options: 'dompdf', 'browsershot', 'wkhtmltopdf'
    |
    */
    //'default' => 'browsershot',
    'default' => 'dompdf',

    /*
    |--------------------------------------------------------------------------
    | DOMPDF Configuration
    |--------------------------------------------------------------------------
    |
    | Fallback para PDFs sin gráficos
    |
    */
    'dompdf' => [
        'options' => [
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => true,      // Permite cargar imágenes remotas
            'isHtml5ParserEnabled' => true,  // Habilita parser HTML5
            'isPhpEnabled' => false,         // Seguridad: no ejecutar PHP
            'isJavascriptEnabled' => false,  // No ejecutar JavaScript
            'fontDir' => storage_path('fonts/'),  // Directorio de fuentes
            'fontCache' => storage_path('fonts/'), // Cache de fuentes
            'tempDir' => storage_path('app/temp/pdf/'), // Directorio temporal
            'logOutputFile' => storage_path('logs/dompdf.log'),

            // ✅ AUMENTAR LÍMITES DE MEMORIA
            'memoryLimit' => '512M',  // Aumentar a 512MB
            'dpi' => 96,              // Reducir DPI para ahorrar memoria
            'enable_html5_parser' => true,
        ],
    ],
];
