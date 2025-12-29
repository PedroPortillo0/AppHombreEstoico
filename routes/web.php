<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDailyQuoteController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\SubscriptionController;

Route::get('/', function () {
    return view('welcome');
});

// ========================================
// RUTAS DE SUSCRIPCIÓN PREMIUM
// ========================================
Route::prefix('subscription')->name('subscription.')->group(function () {
    Route::get('/premium', [SubscriptionController::class, 'showPremium'])->name('premium');
    Route::get('/payment', [SubscriptionController::class, 'showPaymentForm'])->name('payment');
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
    Route::get('/status', [SubscriptionController::class, 'status'])->name('status');
    Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
});

// ========================================
// RUTAS DEL PANEL DE ADMINISTRACIÓN
// ========================================
// Rutas de autenticación de administrador
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
});

// Rutas protegidas del panel de administración
Route::prefix('admin')->middleware('admin.auth')->group(function () {
    // Panel de frases diarias
    Route::prefix('daily-quotes')->name('admin.daily-quotes.')->group(function () {
        Route::get('/', [AdminDailyQuoteController::class, 'index'])->name('index');
        Route::get('/create', [AdminDailyQuoteController::class, 'create'])->name('create');
        Route::post('/', [AdminDailyQuoteController::class, 'store'])->name('store');
        Route::get('/occupied-days', [AdminDailyQuoteController::class, 'getOccupiedDaysApi'])->name('occupied-days');
        Route::get('/{id}/edit', [AdminDailyQuoteController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AdminDailyQuoteController::class, 'update'])->name('update');
        Route::delete('/{id}', [AdminDailyQuoteController::class, 'destroy'])->name('destroy');
    });
});
