<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\EarningsController;
use App\Http\Controllers\Dashboard\CampaignController;
use App\Http\Controllers\Dashboard\CheckInController;
use App\Http\Controllers\Dashboard\CheckInDashboardController;
use App\Http\Controllers\Dashboard\CheckInSetupController;
use App\Http\Controllers\Dashboard\EventController as DashEventController;
use App\Http\Controllers\Dashboard\InsightsController;
use App\Http\Controllers\Dashboard\OfflineCheckInController;
use App\Http\Controllers\Dashboard\PayoutController;
use App\Http\Controllers\Dashboard\PromoCodeController;
use App\Http\Controllers\Dashboard\PromoterController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Dashboard\ReviewController as DashReviewController;
use App\Http\Controllers\Dashboard\SupportInboxController;
use App\Http\Controllers\Dashboard\TeamController;
use App\Http\Controllers\Dashboard\TicketTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TwoFactorController;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::post('/newsletter', function (Request $request) {
    $data = $request->validate(['email' => 'required|email']);
    NewsletterSubscriber::updateOrCreate(
        ['email' => $data['email']],
        ['token' => Str::random(40)],
    );

    return back()->with('success', 'You are subscribed! Check your inbox.');
})->name('newsletter.store');

require __DIR__.'/public.php';

// Team invite links are public so invitees without an account can register from them.
Route::get('/invitations/{token}', [TeamInvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/register', [TeamInvitationController::class, 'register'])
    ->middleware(['guest', 'throttle:10,1'])->name('invitations.register');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile / account settings
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::delete('/settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Two-factor authentication (required for platform administrators)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/settings/two-factor', [TwoFactorController::class, 'store'])->name('two-factor.enable');
        Route::post('/settings/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::post('/settings/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
        Route::delete('/settings/two-factor', [TwoFactorController::class, 'destroy'])->name('two-factor.disable');
    });

    // Organizations
    Route::get('/organizations/new', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

    // Team invitations (the invitee's side)
    Route::post('/invitations/{token}', [TeamInvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');

    // Subscription / billing
    Route::middleware('organizer.approved')->group(function () {
        Route::get('/dashboard/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('/dashboard/subscription', [SubscriptionController::class, 'store'])->name('subscription.store');
        Route::post('/dashboard/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
        Route::post('/dashboard/subscription/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');
        Route::post('/dashboard/subscription/invoices/{number}/pay', [SubscriptionController::class, 'payInvoice'])->middleware('throttle:10,1')->name('subscription.invoices.pay');
        Route::get('/dashboard/subscription/invoices/{number}/callback', [SubscriptionController::class, 'callback'])->name('subscription.callback');
        Route::get('/dashboard/subscription/invoices/{number}/approve', [SubscriptionController::class, 'approve'])->middleware('throttle:40,1')->name('subscription.approve');

    });

    // Organizer events + ticket types
    Route::prefix('dashboard')->name('dashboard.')->middleware('organizer.approved')->group(function () {
        Route::get('events', [DashEventController::class, 'index'])->name('events.index');
        Route::get('events/create', [DashEventController::class, 'create'])->name('events.create');
        Route::post('events', [DashEventController::class, 'store'])->name('events.store');
        Route::get('events/{event}/edit', [DashEventController::class, 'edit'])->name('events.edit');
        Route::patch('events/{event}', [DashEventController::class, 'update'])->name('events.update');
        Route::post('events/{event}/cover', [DashEventController::class, 'updateCover'])->name('events.cover');
        Route::post('events/{event}/publish', [DashEventController::class, 'publish'])->name('events.publish');
        Route::post('events/{event}/ussd', [DashEventController::class, 'updateUssd'])->name('events.ussd');
        Route::post('events/{event}/unpublish', [DashEventController::class, 'unpublish'])->name('events.unpublish');
        Route::post('events/{event}/cancel', [DashEventController::class, 'cancel'])->name('events.cancel');
        Route::post('events/{event}/duplicate', [DashEventController::class, 'duplicate'])->name('events.duplicate');
        Route::delete('events/{event}', [DashEventController::class, 'destroy'])->name('events.destroy');

        Route::post('events/{event}/ticket-types', [TicketTypeController::class, 'store'])->name('ticket-types.store');
        Route::patch('events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'update'])->name('ticket-types.update');
        Route::delete('events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy'])->name('ticket-types.destroy');

        // Check-in / gate scanning
        Route::get('check-in', [CheckInController::class, 'index'])->name('check-in.index');
        Route::get('check-in/{event}', [CheckInController::class, 'scanner'])->name('check-in.scanner');
        Route::post('check-in/{event}/scan', [CheckInController::class, 'scan'])->name('check-in.scan');
        Route::get('check-in/{event}/stats', [CheckInController::class, 'stats'])->name('check-in.stats');
        Route::get('check-in/{event}/live', [CheckInDashboardController::class, 'show'])->name('check-in.live');
        Route::get('check-in/{event}/manifest', [OfflineCheckInController::class, 'manifest'])->middleware('throttle:12,1')->name('check-in.manifest');
        Route::post('check-in/{event}/sync', [OfflineCheckInController::class, 'sync'])->middleware('throttle:60,1')->name('check-in.sync');
        Route::get('check-in/{event}/setup', [CheckInSetupController::class, 'show'])->name('check-in.setup');
        Route::post('check-in/{event}/gates', [CheckInSetupController::class, 'storeGate'])->name('check-in.gates.store');
        Route::patch('check-in/{event}/gates/{gate}', [CheckInSetupController::class, 'updateGate'])->name('check-in.gates.update');
        Route::delete('check-in/{event}/gates/{gate}', [CheckInSetupController::class, 'destroyGate'])->name('check-in.gates.destroy');
        Route::post('check-in/{event}/staff', [CheckInSetupController::class, 'storeStaff'])->name('check-in.staff.store');
        Route::delete('check-in/{event}/staff/{staff}', [CheckInSetupController::class, 'destroyStaff'])->name('check-in.staff.destroy');

        // Team
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::post('team', [TeamController::class, 'store'])->middleware('throttle:20,1')->name('team.store');
        Route::patch('team/{member}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
        Route::post('team/{member}/resend', [TeamController::class, 'resend'])->middleware('throttle:20,1')->name('team.resend');

        // Insights pages
        Route::get('tickets', [InsightsController::class, 'tickets'])->name('tickets.index');
        Route::get('tickets/{code}/print', [TicketController::class, 'print'])->name('tickets.print');
        Route::get('attendees', [InsightsController::class, 'attendees'])->name('attendees.index');
        Route::get('attendees/export', [InsightsController::class, 'exportAttendees'])->middleware('plan:attendee_export')->name('attendees.export');
        Route::get('transactions', [InsightsController::class, 'transactions'])->name('transactions.index');
        Route::get('analytics', [InsightsController::class, 'analytics'])->middleware('plan:advanced_analytics')->name('analytics.index');

        // Reports, discounts, promoters, reviews, support
        Route::middleware('plan:reports')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        });
        Route::middleware('plan:promo_codes')->group(function () {
            Route::get('discounts', [PromoCodeController::class, 'index'])->name('discounts.index');
            Route::post('discounts', [PromoCodeController::class, 'store'])->name('discounts.store');
            Route::post('discounts/{promoCode}/toggle', [PromoCodeController::class, 'toggle'])->name('discounts.toggle');
            Route::delete('discounts/{promoCode}', [PromoCodeController::class, 'destroy'])->name('discounts.destroy');
        });
        Route::middleware('plan:promoters')->group(function () {
            Route::get('promoters', [PromoterController::class, 'index'])->name('promoters.index');
            Route::post('promoters', [PromoterController::class, 'store'])->name('promoters.store');
            Route::post('promoters/{promoter}/toggle', [PromoterController::class, 'toggle'])->name('promoters.toggle');
            Route::delete('promoters/{promoter}', [PromoterController::class, 'destroy'])->name('promoters.destroy');
        });
        Route::get('reviews', [DashReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/toggle', [DashReviewController::class, 'toggle'])->name('reviews.toggle');
        Route::get('support', [SupportInboxController::class, 'index'])->name('support.index');
        Route::post('support/{reference}/reply', [SupportInboxController::class, 'reply'])->name('support.reply');
        Route::post('support/{reference}/close', [SupportInboxController::class, 'close'])->name('support.close');

        // Fundraising campaigns
        Route::get('contributions', [CampaignController::class, 'index'])->name('contributions.index');
        Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');

        // Payouts
        Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
        Route::post('payouts/accounts', [PayoutController::class, 'storeAccount'])->name('payouts.accounts.store');
        Route::post('payouts/request', [PayoutController::class, 'request'])->name('payouts.request');
    });

    // Admin area
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals');
        Route::get('earnings', [EarningsController::class, 'index'])->name('earnings');
        Route::post('earnings/withdraw', [EarningsController::class, 'withdraw'])->middleware('throttle:10,1')->name('earnings.withdraw');
        Route::post('organizations/{organization}/approve', [ApprovalController::class, 'approveOrganization'])->name('organizations.approve');
        Route::post('organizations/{organization}/reject', [ApprovalController::class, 'rejectOrganization'])->name('organizations.reject');
        Route::post('events/{event}/approve', [ApprovalController::class, 'approveEvent'])->name('events.approve');
        Route::post('events/{event}/reject', [ApprovalController::class, 'rejectEvent'])->name('events.reject');
        Route::get('users', [AdminController::class, 'users'])->name('users');
        Route::get('organizers', [AdminController::class, 'organizers'])->name('organizers');
        Route::get('transactions', [AdminController::class, 'transactions'])->name('transactions');
        Route::get('payouts', [AdminController::class, 'payouts'])->name('payouts');
        Route::post('payouts/{payout}/approve', [AdminController::class, 'approvePayout'])->name('payouts.approve');
        Route::get('support', [SupportInboxController::class, 'index'])->name('support.index');
        Route::post('support/{reference}/reply', [SupportInboxController::class, 'reply'])->name('support.reply');
        Route::post('support/{reference}/close', [SupportInboxController::class, 'close'])->name('support.close');
    });

    // In-app notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Customer account
    Route::get('/my-account', fn () => redirect()->route('account.tickets'))->name('account');
    Route::get('/my-account/tickets', [AccountController::class, 'tickets'])->name('account.tickets');
    Route::get('/my-account/tickets/{code}/pdf', [TicketController::class, 'pdf'])->name('account.tickets.pdf');
    Route::get('/my-account/orders', [AccountController::class, 'orders'])->name('account.orders');
});

require __DIR__.'/auth.php';
