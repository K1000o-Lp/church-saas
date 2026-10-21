<?php

use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\ChurchController as AdminChurchController;
use App\Http\Controllers\Admin\ChurchPlanController as AdminChurchPlanController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ChurchAuditController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\ChurchInvitationController;
use App\Http\Controllers\ChurchMemberController;
use App\Http\Controllers\ChurchOnboardingController;
use App\Http\Controllers\ChurchPlanController;
use App\Http\Controllers\CustomFieldDefinitionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\SegmentController;
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

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::get('/invitations/{token}', [InvitationAcceptanceController::class, 'show'])
    ->middleware('throttle:20,1')
    ->name('invitations.show');
Route::post('/invitations/{token}', [InvitationAcceptanceController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('invitations.accept');

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
    Route::put('/churches/{church}/plan', [ChurchPlanController::class, 'update'])
        ->middleware(['church', 'permission:church.plan.manage'])
        ->name('churches.plan.update');

    Route::middleware(['church', 'permission:people.create'])->group(function (): void {
        Route::get('/churches/{church}/people/create', [PersonController::class, 'create'])
            ->name('people.create');
        Route::post('/churches/{church}/people', [PersonController::class, 'store'])
            ->name('people.store');
    });

    Route::middleware(['church', 'permission:people.view'])->group(function (): void {
        Route::get('/churches/{church}/people', [PersonController::class, 'index'])
            ->name('people.index');
        Route::get('/churches/{church}/people/{person}', [PersonController::class, 'show'])
            ->name('people.show');
    });

    Route::middleware(['church', 'permission:people.update'])->group(function (): void {
        Route::get('/churches/{church}/people/{person}/edit', [PersonController::class, 'edit'])
            ->name('people.edit');
        Route::put('/churches/{church}/people/{person}', [PersonController::class, 'update'])
            ->name('people.update');
    });

    Route::middleware(['church', 'permission:people.archive'])->group(function (): void {
        Route::patch('/churches/{church}/people/{person}/archive', [PersonController::class, 'archive'])
            ->name('people.archive');
        Route::patch('/churches/{church}/people/{person}/restore', [PersonController::class, 'restore'])
            ->name('people.restore');
    });

    Route::middleware(['church', 'permission:people.custom_fields.manage'])->group(function (): void {
        Route::get('/churches/{church}/settings/custom-fields', [CustomFieldDefinitionController::class, 'index'])
            ->name('custom-fields.index');
        Route::post('/churches/{church}/settings/custom-fields', [CustomFieldDefinitionController::class, 'store'])
            ->name('custom-fields.store');
        Route::put('/churches/{church}/settings/custom-fields/{definition}', [CustomFieldDefinitionController::class, 'update'])
            ->name('custom-fields.update');
        Route::patch('/churches/{church}/settings/custom-fields/{definition}/toggle', [CustomFieldDefinitionController::class, 'toggle'])
            ->name('custom-fields.toggle');
    });

    Route::get('/churches/{church}/settings/audit', [ChurchAuditController::class, 'index'])
        ->middleware(['church', 'permission:church.audit.view'])
        ->name('churches.audit.index');

    Route::get('/churches/{church}/segments/create', [SegmentController::class, 'create'])
        ->middleware(['church', 'permission:segments.manage'])
        ->name('segments.create');

    Route::middleware(['church', 'permission:segments.view'])->group(function (): void {
        Route::get('/churches/{church}/segments', [SegmentController::class, 'index'])
            ->name('segments.index');
        Route::get('/churches/{church}/segments/preview', [SegmentController::class, 'preview'])
            ->name('segments.preview');
        Route::get('/churches/{church}/segments/{segment}', [SegmentController::class, 'show'])
            ->name('segments.show');
    });

    Route::middleware(['church', 'permission:segments.manage'])->group(function (): void {
        Route::get('/churches/{church}/segments/{segment}/edit', [SegmentController::class, 'edit'])
            ->name('segments.edit');
        Route::post('/churches/{church}/segments', [SegmentController::class, 'store'])
            ->name('segments.store');
        Route::put('/churches/{church}/segments/{segment}', [SegmentController::class, 'update'])
            ->name('segments.update');
        Route::patch('/churches/{church}/segments/{segment}/toggle', [SegmentController::class, 'toggle'])
            ->name('segments.toggle');
        Route::delete('/churches/{church}/segments/{segment}', [SegmentController::class, 'destroy'])
            ->name('segments.destroy');
        Route::post('/churches/{church}/segments/{segment}/members', [SegmentController::class, 'storeMember'])
            ->name('segments.members.store');
        Route::delete('/churches/{church}/segments/{segment}/members/{person}', [SegmentController::class, 'destroyMember'])
            ->name('segments.members.destroy');
    });

    Route::middleware(['church', 'permission:church.members.manage'])->group(function (): void {
        Route::get('/churches/{church}/settings/members', [ChurchMemberController::class, 'index'])
            ->name('churches.members.index');
        Route::put('/churches/{church}/members/{membership}', [ChurchMemberController::class, 'update'])
            ->name('churches.members.update');
        Route::delete('/churches/{church}/members/{membership}', [ChurchMemberController::class, 'destroy'])
            ->name('churches.members.destroy');

        Route::post('/churches/{church}/invitations', [ChurchInvitationController::class, 'store'])
            ->name('churches.invitations.store');
        Route::post('/churches/{church}/invitations/{invitation}/resend', [ChurchInvitationController::class, 'resend'])
            ->name('churches.invitations.resend');
        Route::delete('/churches/{church}/invitations/{invitation}', [ChurchInvitationController::class, 'destroy'])
            ->name('churches.invitations.destroy');
    });
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
