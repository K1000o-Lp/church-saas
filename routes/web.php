<?php

use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\ChurchController as AdminChurchController;
use App\Http\Controllers\Admin\ChurchPlanController as AdminChurchPlanController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ChurchAuditController;
use App\Http\Controllers\ChurchBrandingController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\ChurchInvitationController;
use App\Http\Controllers\ChurchMemberController;
use App\Http\Controllers\ChurchOnboardingController;
use App\Http\Controllers\ChurchPlanController;
use App\Http\Controllers\CustomFieldDefinitionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventCheckInController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\MinistryTeamController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonPipelineController;
use App\Http\Controllers\PersonRelationshipController;
use App\Http\Controllers\PipelineStageController;
use App\Http\Controllers\PublicChurchController;
use App\Http\Controllers\PublicReceiptController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\SegmentController;
use App\Http\Controllers\ServiceItemController;
use App\Http\Controllers\ServicePlanController;
use App\Http\Controllers\VolunteerAssignmentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('/iglesias/{church}', [PublicChurchController::class, 'show'])->name('public.church');
Route::get('/iglesias/{church}/eventos/{eventSlug}', [PublicChurchController::class, 'event'])->name('public.event');
Route::post('/iglesias/{church}/eventos/{eventSlug}/registro', [PublicChurchController::class, 'register'])
    ->middleware('throttle:public-registration')
    ->name('public.event.register');
Route::get('/comprobantes/{token}', [PublicReceiptController::class, 'show'])
    ->middleware('throttle:public-receipt')
    ->name('public.receipt.show');
Route::post('/comprobantes/{token}', [PublicReceiptController::class, 'store'])
    ->middleware('throttle:public-receipt')
    ->name('public.receipt.store');
Route::get('/entradas/{token}', [PublicTicketController::class, 'show'])
    ->middleware('throttle:public-ticket')
    ->name('public.ticket.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-email')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.store');
});

Route::get('/invitations/{token}', [InvitationAcceptanceController::class, 'show'])
    ->middleware('throttle:20,1')
    ->name('invitations.show');
Route::post('/invitations/{token}', [InvitationAcceptanceController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('invitations.accept');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:verification-send')
        ->name('verification.send');

    Route::middleware('verified')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/onboarding/church', [ChurchOnboardingController::class, 'create'])
            ->name('onboarding.church');
        Route::post('/onboarding/church', [ChurchOnboardingController::class, 'store'])
            ->middleware('throttle:church-onboarding')
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
            Route::post('/churches/{church}/people/{person}/relationships', [PersonRelationshipController::class, 'store'])
                ->name('people.relationships.store');
            Route::delete('/churches/{church}/people/{person}/relationships/{relationship}', [PersonRelationshipController::class, 'destroy'])
                ->name('people.relationships.destroy');
        });

        Route::middleware(['church', 'permission:appointments.view'])->group(function (): void {
            Route::get('/churches/{church}/appointments', [AppointmentController::class, 'index'])
                ->name('appointments.index');
            Route::get('/churches/{church}/appointments/{appointment}', [AppointmentController::class, 'show'])
                ->whereNumber('appointment')->name('appointments.show');
        });

        Route::middleware(['church', 'permission:appointments.manage'])->group(function (): void {
            Route::get('/churches/{church}/appointments/create', [AppointmentController::class, 'create'])
                ->name('appointments.create');
            Route::post('/churches/{church}/appointments', [AppointmentController::class, 'store'])
                ->name('appointments.store');
            Route::get('/churches/{church}/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])
                ->whereNumber('appointment')->name('appointments.edit');
            Route::put('/churches/{church}/appointments/{appointment}', [AppointmentController::class, 'update'])
                ->whereNumber('appointment')->name('appointments.update');
            Route::patch('/churches/{church}/appointments/{appointment}/complete', [AppointmentController::class, 'complete'])
                ->whereNumber('appointment')->name('appointments.complete');
            Route::patch('/churches/{church}/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
                ->whereNumber('appointment')->name('appointments.cancel');
            Route::patch('/churches/{church}/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])
                ->whereNumber('appointment')->name('appointments.reschedule');
            Route::patch('/churches/{church}/appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])
                ->whereNumber('appointment')->name('appointments.no-show');
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

        Route::middleware(['church', 'permission:pastoral.view', 'plan.feature:pastoral_pipeline'])->group(function (): void {
            Route::get('/churches/{church}/settings/pipeline', [PipelineStageController::class, 'index'])
                ->name('pipeline.index');
        });

        Route::middleware(['church', 'permission:pastoral.manage', 'plan.feature:pastoral_pipeline'])->group(function (): void {
            Route::post('/churches/{church}/settings/pipeline', [PipelineStageController::class, 'store'])
                ->name('pipeline.store');
            Route::put('/churches/{church}/settings/pipeline/{stage}', [PipelineStageController::class, 'update'])
                ->whereNumber('stage')->name('pipeline.update');
            Route::post('/churches/{church}/people/{person}/pipeline/{stage}', [PersonPipelineController::class, 'complete'])
                ->whereNumber('person')->whereNumber('stage')->name('people.pipeline.complete');
            Route::delete('/churches/{church}/people/{person}/pipeline/{stage}', [PersonPipelineController::class, 'reopen'])
                ->whereNumber('person')->whereNumber('stage')->name('people.pipeline.reopen');
        });

        Route::middleware(['church', 'permission:church.settings.manage', 'plan.feature:white_label'])->group(function (): void {
            Route::get('/churches/{church}/settings/branding', [ChurchBrandingController::class, 'edit'])
                ->name('churches.branding.edit');
            Route::post('/churches/{church}/settings/branding', [ChurchBrandingController::class, 'update'])
                ->name('churches.branding.update');
        });

        Route::get('/churches/{church}/settings/audit', [ChurchAuditController::class, 'index'])
            ->middleware(['church', 'permission:church.audit.view'])
            ->name('churches.audit.index');

        Route::get('/churches/{church}/segments/create', [SegmentController::class, 'create'])
            ->middleware(['church', 'permission:segments.manage'])
            ->name('segments.create');

        Route::middleware(['church', 'permission:events.view'])->group(function (): void {
            Route::get('/churches/{church}/events', [EventController::class, 'index'])
                ->name('events.index');
            Route::get('/churches/{church}/events/{event}', [EventController::class, 'show'])
                ->whereNumber('event')->name('events.show');
            Route::get('/churches/{church}/events/{event}/registrations', [EventRegistrationController::class, 'index'])
                ->whereNumber('event')->name('events.registrations.index');
            Route::get('/churches/{church}/events/{event}/attendance', [AttendanceController::class, 'index'])
                ->whereNumber('event')->name('events.attendance.index');
        });

        Route::middleware(['church', 'permission:events.manage'])->group(function (): void {
            Route::get('/churches/{church}/events/create', [EventController::class, 'create'])
                ->name('events.create');
            Route::post('/churches/{church}/events', [EventController::class, 'store'])
                ->name('events.store');
            Route::get('/churches/{church}/events/{event}/edit', [EventController::class, 'edit'])
                ->whereNumber('event')->name('events.edit');
            Route::put('/churches/{church}/events/{event}', [EventController::class, 'update'])
                ->whereNumber('event')->name('events.update');
            Route::patch('/churches/{church}/events/{event}/publish', [EventController::class, 'publish'])
                ->whereNumber('event')->name('events.publish');
            Route::patch('/churches/{church}/events/{event}/cancel', [EventController::class, 'cancel'])
                ->whereNumber('event')->name('events.cancel');
            Route::patch('/churches/{church}/events/{event}/complete', [EventController::class, 'complete'])
                ->whereNumber('event')->name('events.complete');
            Route::post('/churches/{church}/events/{event}/registrations', [EventRegistrationController::class, 'store'])
                ->whereNumber('event')->name('events.registrations.store');
            Route::patch('/churches/{church}/events/{event}/registrations/{registration}', [EventRegistrationController::class, 'updateStatus'])
                ->whereNumber('event')->name('events.registrations.update');
            Route::delete('/churches/{church}/events/{event}/registrations/{registration}', [EventRegistrationController::class, 'destroy'])
                ->whereNumber('event')->name('events.registrations.destroy');
            Route::get('/churches/{church}/events/{event}/receipts', [PaymentReceiptController::class, 'index'])
                ->whereNumber('event')->name('events.receipts.index');
            Route::patch('/churches/{church}/events/{event}/receipts/{receipt}/verify', [PaymentReceiptController::class, 'verify'])
                ->whereNumber('event')->name('events.receipts.verify');
            Route::patch('/churches/{church}/events/{event}/receipts/{receipt}/reject', [PaymentReceiptController::class, 'reject'])
                ->whereNumber('event')->name('events.receipts.reject');
            Route::get('/churches/{church}/events/{event}/receipts/{receipt}/download', [PaymentReceiptController::class, 'download'])
                ->whereNumber('event')->name('events.receipts.download');
        });

        Route::middleware(['church', 'permission:attendance.record'])->group(function (): void {
            Route::post('/churches/{church}/events/{event}/attendance', [AttendanceController::class, 'store'])
                ->whereNumber('event')->name('events.attendance.store');
            Route::get('/churches/{church}/events/{event}/check-in', [EventCheckInController::class, 'index'])
                ->whereNumber('event')->name('events.check-in.index');
            Route::post('/churches/{church}/events/{event}/check-in', [EventCheckInController::class, 'store'])
                ->whereNumber('event')->name('events.check-in.store');
        });

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

        Route::middleware(['church', 'plan.feature:volunteers', 'permission:teams.view'])->group(function (): void {
            Route::get('/churches/{church}/services', [ServicePlanController::class, 'index'])
                ->name('services.index');
            Route::get('/churches/{church}/services/teams', [MinistryTeamController::class, 'index'])
                ->name('services.teams');
            Route::get('/churches/{church}/services/{service}', [ServicePlanController::class, 'show'])
                ->whereNumber('service')->name('services.show');
        });

        Route::middleware(['church', 'plan.feature:volunteers', 'permission:teams.manage'])->group(function (): void {
            Route::get('/churches/{church}/services/create', [ServicePlanController::class, 'create'])
                ->name('services.create');
            Route::post('/churches/{church}/services', [ServicePlanController::class, 'store'])
                ->name('services.store');
            Route::get('/churches/{church}/services/{service}/edit', [ServicePlanController::class, 'edit'])
                ->whereNumber('service')->name('services.edit');
            Route::put('/churches/{church}/services/{service}', [ServicePlanController::class, 'update'])
                ->whereNumber('service')->name('services.update');
            Route::patch('/churches/{church}/services/{service}/publish', [ServicePlanController::class, 'publish'])
                ->whereNumber('service')->name('services.publish');
            Route::patch('/churches/{church}/services/{service}/cancel', [ServicePlanController::class, 'cancel'])
                ->whereNumber('service')->name('services.cancel');
            Route::patch('/churches/{church}/services/{service}/complete', [ServicePlanController::class, 'complete'])
                ->whereNumber('service')->name('services.complete');

            Route::post('/churches/{church}/services/{service}/items', [ServiceItemController::class, 'store'])
                ->whereNumber('service')->name('services.items.store');
            Route::put('/churches/{church}/services/{service}/items/{item}', [ServiceItemController::class, 'update'])
                ->whereNumber('service')->whereNumber('item')->name('services.items.update');
            Route::delete('/churches/{church}/services/{service}/items/{item}', [ServiceItemController::class, 'destroy'])
                ->whereNumber('service')->whereNumber('item')->name('services.items.destroy');

            Route::post('/churches/{church}/services/teams', [MinistryTeamController::class, 'store'])
                ->name('services.teams.store');
            Route::put('/churches/{church}/services/teams/{team}', [MinistryTeamController::class, 'update'])
                ->whereNumber('team')->name('services.teams.update');
        });

        Route::middleware(['church', 'plan.feature:volunteers', 'permission:volunteers.manage'])->group(function (): void {
            Route::post('/churches/{church}/services/{service}/items/{item}/assignments', [VolunteerAssignmentController::class, 'store'])
                ->whereNumber('service')->whereNumber('item')->name('services.assignments.store');
            Route::patch('/churches/{church}/services/{service}/items/{item}/assignments/{assignment}', [VolunteerAssignmentController::class, 'updateStatus'])
                ->whereNumber('service')->whereNumber('item')->whereNumber('assignment')->name('services.assignments.update');
            Route::delete('/churches/{church}/services/{service}/items/{item}/assignments/{assignment}', [VolunteerAssignmentController::class, 'destroy'])
                ->whereNumber('service')->whereNumber('item')->whereNumber('assignment')->name('services.assignments.destroy');
        });

        Route::middleware(['church', 'permission:church.members.manage'])->group(function (): void {
            Route::get('/churches/{church}/settings/members', [ChurchMemberController::class, 'index'])
                ->name('churches.members.index');
            Route::put('/churches/{church}/members/{membership}', [ChurchMemberController::class, 'update'])
                ->name('churches.members.update');
            Route::delete('/churches/{church}/members/{membership}', [ChurchMemberController::class, 'destroy'])
                ->name('churches.members.destroy');

            Route::post('/churches/{church}/invitations', [ChurchInvitationController::class, 'store'])
                ->middleware('throttle:church-invitations')
                ->name('churches.invitations.store');
            Route::post('/churches/{church}/invitations/{invitation}/resend', [ChurchInvitationController::class, 'resend'])
                ->middleware('throttle:church-invitations')
                ->name('churches.invitations.resend');
            Route::delete('/churches/{church}/invitations/{invitation}', [ChurchInvitationController::class, 'destroy'])
                ->name('churches.invitations.destroy');
        });
    });
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
    Route::post('/login', [AdminSessionController::class, 'store'])
        ->middleware('throttle:admin-login')
        ->name('login.store');

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
