<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TrackingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
class OrderController extends Controller
{

    // Tambahkan ini di dalam class OrderController
public function index()
{
    try {
        // Mengambil semua data order diurutkan dari yang terbaru
        $orders = Order::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'message' => 'Daftar semua pesanan',
            'data' => $orders
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Gagal mengambil data',
            'error' => $e->getMessage()
        ], 500);
    }
}
    // 1. Simpan Pesanan Baru (Admin)
    public function store(Request $request)
{
    try {
        $request->validate([
            'customer_name' => 'required',
            'wa_number' => 'required',
            'address' => 'required',
            'weight' => 'required',
            'service' => 'required',
        ]);

        $orderCode = 'LDR-' . strtoupper(Str::random(6));

        $order = Order::create([
            'order_code'    => $orderCode,
            'customer_name' => $request->customer_name,
            'wa_number'     => $request->wa_number,
            'address'       => $request->address,
            'weight'        => $request->weight,
            'service'       => $request->service,
            'status'        => 'Menunggu Pembayaran',
        ]);

        // PERHATIKAN DISINI: Harus pakai panah (->) bukan titik (.)
        TrackingLog::create([
            'order_id' => $order->id, // <--- Pastikan ini pakai ->
            'status'   => 'Pesanan Dibuat',
            'message'  => 'Pesanan telah diterima oleh Lyra Laundry.',
        ]);

        return response()->json(['message' => 'Order Created', 'data' => $order], 201);
        
    } catch (\Exception $e) {
        // Ini biar kalau error, kita dapet pesan jelas di Postman
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    // 2. Ambil Detail Tracking (Untuk Tracking Page Flutter)
    public function show($code)
    {
        $order = Order::with('logs')->where('order_code', $code)->first();

        if (!$order) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        return response()->json(['data' => $order], 200);
    }

    // 3. Update Lokasi Kurir (Real-time dari Kurir Dashboard)
    public function updateLocation(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->update([
            'courier_lat' => $request->lat,
            'courier_lng' => $request->lng,
            'status'      => 'Sedang Diantar',
        ]);

        return response()->json(['message' => 'Location Updated'], 200);
    }

    // 4. Update Status Pembayaran
    public function payment(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'Sudah Dibayar']);

        TrackingLog::create([
            'order_id' => $order->id,
            'status'   => 'Pembayaran Berhasil',
            'message'  => 'Pesanan telah lunas. Terima kasih!',
        ]);

        return response()->json(['message' => 'Payment Success'], 200);
    }

    // Update Lokasi Kurir & Status (Dipanggil dari HP Kurir tiap beberapa meter)
   

   public function financialReport()
    {
        $hargaPerKilo = 7000; 
        
        $completedOrders = Order::where('status', '!=', 'Menunggu Pembayaran');

        $dailyWeight = (float) ((clone $completedOrders)->whereDate('created_at', Carbon::today())->sum('weight'));
        $daily = $dailyWeight * $hargaPerKilo;

        $weeklyWeight = (float) ((clone $completedOrders)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->sum('weight'));
        $weekly = $weeklyWeight * $hargaPerKilo;

        $monthlyWeight = (float) ((clone $completedOrders)->whereMonth('created_at', Carbon::now()->month)
                                                        ->whereYear('created_at', Carbon::now()->year)->sum('weight'));
        $monthly = $monthlyWeight * $hargaPerKilo;

        $yearlyWeight = (float) ((clone $completedOrders)->whereYear('created_at', Carbon::now()->year)->sum('weight'));
        $yearly = $yearlyWeight * $hargaPerKilo;

        // --- TAMBAHAN BARU: Hitung Jumlah Pesanan ---
        $completedCount = Order::where('status', 'Selesai')->count();
        $processCount = Order::where('status', '!=', 'Selesai')
                             ->where('status', '!=', 'Menunggu Pembayaran')
                             ->count();

        return response()->json([
            'data' => [
                'daily' => $daily,
                'weekly' => $weekly,
                'monthly' => $monthly, // Ini yang akan kita pakai di Dashboard
                'yearly' => $yearly,
                'completed_count' => $completedCount, // Jumlah selesai
                'process_count' => $processCount,     // Jumlah proses
            ]
        ]);
    }
}