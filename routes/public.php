<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UssdController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Event discovery
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');
Route::post('/events/{event:slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:10,1')->name('events.reviews.store');

// Checkout (guest checkout allowed)
Route::post('/events/{event:slug}/checkout', [CheckoutController::class, 'start'])->name('checkout.start');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::post('/checkout/promo', [CheckoutController::class, 'applyPromo'])->middleware('throttle:20,1')->name('checkout.promo.apply');
Route::delete('/checkout/promo', [CheckoutController::class, 'removePromo'])->name('checkout.promo.remove');
Route::get('/checkout/{reference}/callback', [CheckoutController::class, 'callback'])->name('checkout.callback');
Route::get('/checkout/{reference}/complete', [CheckoutController::class, 'complete'])->name('checkout.complete');
Route::post('/checkout/{reference}/pay', [CheckoutController::class, 'retryPayment'])->middleware('throttle:10,1')->name('checkout.retry');
Route::get('/checkout/{reference}/approve', [CheckoutController::class, 'approve'])->middleware('throttle:40,1')->name('checkout.approve');

// Ticket download from the emailed/confirmation link (guest buyers, no account)
Route::get('/tickets/{code}/download', [TicketController::class, 'download'])->middleware('signed')->name('tickets.download');

// Payment webhooks (no CSRF; signature-verified in the service)
Route::post('/webhooks/flutterwave', [WebhookController::class, 'flutterwave'])->name('webhooks.flutterwave');
Route::post('/webhooks/paytota', [WebhookController::class, 'paytota'])->name('webhooks.paytota');
Route::post('/webhooks/ussd', UssdController::class)->middleware('throttle:120,1')->name('webhooks.ussd');

// Uploaded images kept in the database (PUBLIC_DISK_DRIVER=database)
Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.+')->name('media.show');

// Scheduler trigger for hosts without cron (token-checked in the controller)
Route::post('/internal/cron', CronController::class)->middleware('throttle:10,1')->name('internal.cron');

// Fundraising / contributions (public)
Route::get('/campaigns/{campaign:slug}', [ContributionController::class, 'show'])->name('campaigns.show');
Route::post('/campaigns/{campaign:slug}/contribute', [ContributionController::class, 'contribute'])->name('campaigns.contribute');
Route::get('/contribute/{reference}/callback', [ContributionController::class, 'callback'])->name('contribute.callback');
Route::get('/contribute/{reference}/complete', [ContributionController::class, 'complete'])->name('contribute.complete');

// Search engines
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Static / marketing pages
Route::get('/categories', [PageController::class, 'categories'])->name('categories');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');

// Customer support requests (guests welcome)
Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:5,1')->name('support.store');
