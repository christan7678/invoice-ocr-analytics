<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceUploadController;
use App\Http\Controllers\OcrEvaluationController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/company/setup', [CompanyController::class, 'create'])->name('company.create');
    Route::post('/company/setup', [CompanyController::class, 'store'])->name('company.store');
    Route::get('/company', [CompanyController::class, 'edit'])->middleware('company.profile')->name('company.edit');
    Route::put('/company', [CompanyController::class, 'update'])->middleware('company.profile')->name('company.update');
});

Route::middleware(['auth', 'verified', 'company.profile'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/invoice-upload', [InvoiceUploadController::class, 'index'])->name('invoice-upload.index');
    Route::post('/invoice-upload', [InvoiceUploadController::class, 'store'])->name('invoice-upload.store');
    Route::get('/documents/{document}', [InvoiceUploadController::class, 'show'])->name('documents.show');
    Route::get('/exchange-rate', [ExchangeRateController::class, 'show'])->name('exchange-rate.show');
    Route::resource('/invoices', InvoiceController::class);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
    Route::get('/insights', InsightController::class)->name('insights.index');
    Route::get('/ocr-evaluation', OcrEvaluationController::class)->name('ocr-evaluation.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
