<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route; // Pastikan ini ada
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\PaymentController;

// --- PUBLIC ROUTES (Bisa diakses tanpa login) ---
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/orders/{code}', [OrderController::class, 'show']); // Pelanggan track tanpa login
//mitrans
Route::post('/payment/token', [PaymentController::class, 'getSnapToken']);
Route::post('/payment/callback', [PaymentController::class, 'callback']);
Route::post('/orders', [OrderController::class, 'store']);

// --- PROTECTED ROUTES (Hanya Admin/Kurir yang punya Token) ---
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Laporan Keuangan (Dipindah ke dalam grup biar rapi)
    Route::get('/reports/financial', [OrderController::class, 'financialReport']);
    
    // Fitur Laundry Management (Pesanan)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/{id}/pay', [OrderController::class, 'payment']);
    // PASTIKAN MENGGUNAKAN Route::post
Route::post('/orders/{id}/image', [\App\Http\Controllers\Api\OrderController::class, 'updateImage']);
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus']); // <--- PENTING UNTUK KURIR
    Route::post('/orders/{id}/location', [OrderController::class, 'updateLocation']);
    Route::post('/orders/{id}/weight', [OrderController::class, 'updateWeight']);
    
    // Rute Manajemen Kurir
Route::get('/couriers', [AuthController::class, 'getCouriers']);
    // Route::post('/couriers', [AuthController::class, 'storeCourier']);
  // HAPUS yang Route::post('/couriers/{id}', ...)
// CUKUP gunakan yang ini:
Route::put('/couriers/{id}', [AuthController::class, 'updateCourier']);
// Dan pastikan ada method POST untuk simpan baru
Route::post('/couriers', [AuthController::class, 'storeCourier']);
    // Route::post('/couriers/{id}', [AuthController::class, 'updateCourier']); // Gunakan POST untuk upload file
    Route::delete('/couriers/{id}', [AuthController::class, 'destroyCourier']);
});