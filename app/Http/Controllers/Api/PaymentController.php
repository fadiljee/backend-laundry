<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Snap;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function getSnapToken(Request $request)
{
    $params = [
        'transaction_details' => [
            'order_id' => 'LYRA-' . time(),
            'gross_amount' => (int) $request->total_harga,
        ],
        // BAGIAN PENTING: Paksa hanya munculkan QRIS
        'enabled_payments' => ['other_qris'],
        
        'customer_details' => [
            'first_name' => $request->nama,
            'email' => $request->email,
        ],
        'item_details' => [
            [
                'id' => 'LAUNDRY-01',
                'price' => (int) $request->total_harga,
                'quantity' => 1,
                'name' => "Layanan Laundry Lyra",
            ]
        ],
    ];

    try {
        $snapToken = \Midtrans\Snap::getSnapToken($params);
        return response()->json(['token' => $snapToken]);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
}

    public function callback(Request $request)
    {
        $serverKey = env('MIDTRANS_SERVER_KEY');
        $hashed = hash("sha512", $request->order_id . $request->status_code . $request->gross_amount . $serverKey);

        if ($hashed == $request->signature_key) {
            if ($request->transaction_status == 'settlement') {
                // Di sini logika update database laundry kamu
                // Contoh: Order::where('order_id', $request->order_id)->update(['status' => 'Lunas']);
            }
        }
        
        return response()->json(['message' => 'success']);
    }
}