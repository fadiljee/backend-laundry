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

// --- PROTECTED ROUTES (Hanya Admin/Kurir yang punya Token) ---
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Laporan Keuangan (Dipindah ke dalam grup biar rapi)
    Route::get('/reports/financial', [OrderController::class, 'financialReport']);
    
    // Fitur Laundry Management (Pesanan)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{id}/pay', [OrderController::class, 'payment']);
    // PASTIKAN MENGGUNAKAN Route::post
Route::post('/orders/{id}/image', [\App\Http\Controllers\Api\OrderController::class, 'updateImage']);
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus']); // <--- PENTING UNTUK KURIR
    Route::post('/orders/{id}/location', [OrderController::class, 'updateLocation']);
    
    // Rute Manajemen Kurir
    Route::get('/couriers', [CourierController::class, 'index']);
    Route::post('/couriers', [CourierController::class, 'store']);
    Route::put('/couriers/{id}', [CourierController::class, 'update']);
    Route::delete('/couriers/{id}', [CourierController::class, 'destroy']);
    

});