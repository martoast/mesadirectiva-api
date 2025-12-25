<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventItemController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\PublicSeatingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SeatController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\TicketTierController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

// API Info
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'version' => '1.0.0',
        'status' => 'ok',
    ]);
});

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Google OAuth
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);
});

// Public Event Pages
Route::prefix('public')->group(function () {
    Route::get('/events', [PublicEventController::class, 'index']);
    Route::get('/events/{slug}', [PublicEventController::class, 'show']);
    Route::get('/events/{slug}/availability', [PublicEventController::class, 'availability']);

    // Ticket Tiers (for General Admission events)
    Route::get('/events/{slug}/ticket-tiers', [PublicSeatingController::class, 'ticketTiers']);

    // Tables & Seats (for Seated events)
    Route::get('/events/{slug}/tables', [PublicSeatingController::class, 'tables']);
    Route::get('/events/{slug}/tables/{tableId}/seats', [PublicSeatingController::class, 'seats']);

    // Reservations
    Route::post('/events/{slug}/reserve', [PublicSeatingController::class, 'reserve']);
    Route::delete('/events/{slug}/reserve', [PublicSeatingController::class, 'releaseReservation']);
});

// Checkout (public but requires event to be live)
Route::post('/checkout/create-session', [CheckoutController::class, 'createSession']);

// Stripe Webhooks
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Authentication Required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/events/{slug}/stats', [DashboardController::class, 'eventStats']);
    });

    // Groups
    Route::apiResource('groups', GroupController::class);

    // Events
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{slug}', [EventController::class, 'show']);
        Route::put('/{slug}', [EventController::class, 'update']);
        Route::delete('/{slug}', [EventController::class, 'destroy']);

        // Event Actions
        Route::post('/{slug}/publish', [EventController::class, 'publish']);
        Route::post('/{slug}/close', [EventController::class, 'close']);
        Route::post('/{slug}/toggle-registration', [EventController::class, 'toggleRegistration']);
        Route::post('/{slug}/duplicate', [EventController::class, 'duplicate']);
        Route::post('/{slug}/hero-image', [EventController::class, 'uploadHeroImage']);

        // Event Items
        Route::get('/{slug}/items', [EventItemController::class, 'index']);
        Route::post('/{slug}/items', [EventItemController::class, 'store']);
        Route::put('/{slug}/items/{itemId}', [EventItemController::class, 'update']);
        Route::delete('/{slug}/items/{itemId}', [EventItemController::class, 'destroy']);

        // Ticket Tiers (for General Admission events)
        Route::get('/{slug}/ticket-tiers', [TicketTierController::class, 'index']);
        Route::post('/{slug}/ticket-tiers', [TicketTierController::class, 'store']);
        Route::get('/{slug}/ticket-tiers/{tierId}', [TicketTierController::class, 'show']);
        Route::put('/{slug}/ticket-tiers/{tierId}', [TicketTierController::class, 'update']);
        Route::delete('/{slug}/ticket-tiers/{tierId}', [TicketTierController::class, 'destroy']);

        // Tables (for Seated events)
        Route::get('/{slug}/tables', [TableController::class, 'index']);
        Route::post('/{slug}/tables', [TableController::class, 'store']);
        Route::post('/{slug}/tables/bulk', [TableController::class, 'bulkStore']);
        Route::get('/{slug}/tables/{tableId}', [TableController::class, 'show']);
        Route::put('/{slug}/tables/{tableId}', [TableController::class, 'update']);
        Route::delete('/{slug}/tables/{tableId}', [TableController::class, 'destroy']);

        // Seats (for tables with sell_as_whole = false)
        Route::get('/{slug}/tables/{tableId}/seats', [SeatController::class, 'index']);
        Route::post('/{slug}/tables/{tableId}/seats', [SeatController::class, 'store']);
        Route::post('/{slug}/tables/{tableId}/seats/bulk', [SeatController::class, 'bulkStore']);
        Route::put('/{slug}/seats/{seatId}', [SeatController::class, 'update']);
        Route::delete('/{slug}/seats/{seatId}', [SeatController::class, 'destroy']);

        // Event Orders
        Route::get('/{slug}/orders', [EventController::class, 'orders']);
    });

    // Users (Super Admin only)
    Route::middleware('can:manage-users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/groups', [UserController::class, 'assignGroups']);
        Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [ReportController::class, 'sales']);
        Route::get('/sales/export', [ReportController::class, 'exportSales']);
        Route::get('/orders', [ReportController::class, 'orders']);
        Route::get('/orders/export', [ReportController::class, 'exportOrders']);
    });

    // Orders
    Route::get('/orders/{orderNumber}', [CheckoutController::class, 'showOrder']);
});
