<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Event discovery
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');

// Checkout (guest checkout allowed)
Route::post('/events/{event:slug}/checkout', [CheckoutController::class, 'start'])->name('checkout.start');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/{reference}/callback', [CheckoutController::class, 'callback'])->name('checkout.callback');
Route::get('/checkout/{reference}/complete', [CheckoutController::class, 'complete'])->name('checkout.complete');

// Ticket download from the emailed/confirmation link (guest buyers, no account)
Route::get('/tickets/{code}/download', [TicketController::class, 'download'])->middleware('signed')->name('tickets.download');

// Payment webhooks (no CSRF; signature-verified in the service)
Route::post('/webhooks/flutterwave', [WebhookController::class, 'flutterwave'])->name('webhooks.flutterwave');

// Fundraising / contributions (public)
Route::get('/campaigns/{campaign:slug}', [ContributionController::class, 'show'])->name('campaigns.show');
Route::post('/campaigns/{campaign:slug}/contribute', [ContributionController::class, 'contribute'])->name('campaigns.contribute');
Route::get('/contribute/{reference}/callback', [ContributionController::class, 'callback'])->name('contribute.callback');
Route::get('/contribute/{reference}/complete', [ContributionController::class, 'complete'])->name('contribute.complete');

// Static / marketing pages
Route::get('/categories', [PageController::class, 'categories'])->name('categories');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
