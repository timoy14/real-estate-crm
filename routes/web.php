<?php

use App\Http\Controllers\App\ActivityController;
use App\Http\Controllers\App\AssistantController;
use App\Http\Controllers\App\ClientBulkController;
use App\Http\Controllers\App\ClientController;
use App\Http\Controllers\App\DealController;
use App\Http\Controllers\App\InquiryController;
use App\Http\Controllers\App\MyDayController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\PropertyController;
use App\Http\Controllers\App\PropertyPhotoController;
use App\Http\Controllers\App\TaskController;
use App\Http\Controllers\App\WishlistController;
use App\Http\Controllers\Public\InquirySubmissionController;
use App\Http\Controllers\Public\ListingController;
use Illuminate\Support\Facades\Route;

// Public listings site (no login).
Route::get('/', [ListingController::class, 'index'])->name('home');
Route::get('properties/{slug}', [ListingController::class, 'show'])->name('listings.show');
Route::post('properties/{slug}/inquiries', [InquirySubmissionController::class, 'store'])
    ->middleware('throttle:inquiries')->name('listings.inquire');

// Staff CRM. Every route in this group requires an authenticated, verified user.
Route::middleware(['auth', 'verified'])->prefix('app')->name('app.')->group(function () {
    Route::redirect('/', '/app/my-day');
    Route::get('my-day', MyDayController::class)->name('my-day');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->whereUuid('id')->name('notifications.read');

    Route::post('clients/bulk', ClientBulkController::class)->name('clients.bulk');
    Route::resource('clients', ClientController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('clients/{client}/wishlists', [WishlistController::class, 'store'])->name('clients.wishlists.store');
    Route::patch('wishlists/{wishlist}', [WishlistController::class, 'update'])->name('clients.wishlists.update');
    Route::delete('wishlists/{wishlist}', [WishlistController::class, 'destroy'])->name('clients.wishlists.destroy');

    Route::resource('deals', DealController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::patch('deals/{deal}/move', [DealController::class, 'move'])->name('deals.move');

    Route::get('inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
    Route::patch('inquiries/{inquiry}', [InquiryController::class, 'update'])->name('inquiries.update');

    Route::resource('activities', ActivityController::class)->only(['store', 'update', 'destroy']);
    Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('assistant/ask', [AssistantController::class, 'ask'])->middleware('throttle:assistant')->name('assistant.ask');
    Route::get('assistant/{conversation}', [AssistantController::class, 'show'])->whereNumber('conversation')->name('assistant.show');

    Route::resource('tasks', TaskController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('properties', PropertyController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('properties/{property}/photos', [PropertyPhotoController::class, 'store'])->name('properties.photos.store');
    Route::patch('properties/{property}/photos/order', [PropertyPhotoController::class, 'order'])->name('properties.photos.order');
    Route::delete('properties/{property}/photos/{photo}', [PropertyPhotoController::class, 'destroy'])->name('properties.photos.destroy');
});

require __DIR__.'/settings.php';
