<?php

use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\ChurchController as AdminChurchController;
use App\Http\Controllers\Admin\ChurchPlanController as AdminChurchPlanController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\ChurchOnboardingController;
use App\Http\Controllers\ChurchPlanController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/onboarding/church', [ChurchOnboardingController::class, 'create'])
        ->name('onboarding.church');
    Route::post('/onboarding/church', [ChurchOnboardingController::class, 'store'])
        ->name('onboarding.church.store');

    Route::get('/churches/{church}', [ChurchController::class, 'show'])
        ->middleware('church')
        ->name('churches.show');

    Route::get('/churches/{church}/plan', [ChurchPlanController::class, 'show'])
        ->middleware('church')
        ->name('churches.plan');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
    Route::post('/login', [AdminSessionController::class, 'store'])->name('login.store');

    Route::middleware('platform')->group(function (): void {
        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
        Route::redirect('/', '/admin/churches')->name('home');

        Route::get('/churches', [AdminChurchController::class, 'index'])->name('churches.index');
        Route::get('/churches/{church}', [AdminChurchController::class, 'show'])->name('churches.show');
        Route::put('/churches/{church}', [AdminChurchController::class, 'update'])->name('churches.update');
        Route::put('/churches/{church}/status', [AdminChurchController::class, 'updateStatus'])->name('churches.status');

        Route::get('/churches/{church}/plan', [AdminChurchPlanController::class, 'show'])->name('churches.plan');
        Route::put('/churches/{church}/plan/subscription', [AdminChurchPlanController::class, 'updateSubscription'])->name('churches.plan.subscription');
        Route::put('/churches/{church}/plan/limits', [AdminChurchPlanController::class, 'updateLimits'])->name('churches.plan.limits');
        Route::put('/churches/{church}/plan/features', [AdminChurchPlanController::class, 'updateFeatures'])->name('churches.plan.features');

        Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/churches/{church}/invoices', [AdminInvoiceController::class, 'forChurch'])->name('churches.invoices');
        Route::post('/churches/{church}/invoices', [AdminInvoiceController::class, 'store'])->name('churches.invoices.store');
        Route::put('/invoices/{invoice}/status', [AdminInvoiceController::class, 'updateStatus'])->name('invoices.status');
        Route::post('/invoices/{invoice}/payments', [AdminInvoiceController::class, 'storePayment'])->name('invoices.payments.store');
    });
});
