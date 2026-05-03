<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route; 
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\PaymentController;

// ─────────────────────────────────────────────────────────────
// --- PUBLIC ROUTES (Bisa diakses tanpa login token) ---
// ─────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/orders/{code}', [OrderController::class, 'show']); 

// Pindahkan rute update lokasi kurir ke sini! 👇
Route::put('/orders/{order_code}/update-location', [OrderController::class, 'updateCourierLocation']);

// midtrans
Route::post('/payment/token', [PaymentController::class, 'getSnapToken']);
Route::post('/payment/callback', [PaymentController::class, 'callback']);
Route::post('/orders', [OrderController::class, 'store']);


// ─────────────────────────────────────────────────────────────
// --- PROTECTED ROUTES (Hanya Admin/Kurir yang punya Token) ---
// ─────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Laporan Keuangan
    Route::get('/reports/financial', [OrderController::class, 'financialReport']);
    
    // Fitur Laundry Management (Pesanan)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/{id}/pay', [OrderController::class, 'payment']);
    Route::post('/orders/{id}/image', [OrderController::class, 'updateImage']);
    Route::post('/orders/{id}/location', [OrderController::class, 'updateLocation']);
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus']); 
    Route::post('/orders/{id}/weight', [OrderController::class, 'updateWeight']);
    
    // Baris update-location yang sebelumnya di sini SUDAH DIHAPUS / DIPINDAH KE ATAS
    
    // Rute Manajemen Kurir
    Route::get('/couriers', [AuthController::class, 'getCouriers']);
    Route::put('/couriers/{id}', [AuthController::class, 'updateCourier']);
    Route::post('/couriers', [AuthController::class, 'storeCourier']);
    Route::delete('/couriers/{id}', [AuthController::class, 'destroyCourier']);
});