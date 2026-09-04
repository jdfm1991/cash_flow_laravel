<?php

use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('reports')->middleware(['auth'])->group(function () {
    Route::get('/cash-flow', [ReportController::class, 'previewCashFlow'])->name('reports.cash-flow.preview');
    Route::get('/cash-flow/download', [ReportController::class, 'cashFlow'])->name('reports.cash-flow.download');
});
