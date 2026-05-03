<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TrackingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    // --- KONFIGURASI HARGA & SATUAN ---
    private $daftarHarga = [
        'Cuci Lipat'        => 5000,
        'Setrika'           => 5000,
        'Cuci Setrika'      => 5000,
        'Express 1 Hari'    => 12000,
        'Cuci Basah'        => 4000,
        'Bed Cover Besar'   => 35000,
        'Bed Cover Kecil'   => 20000,
        'Sprei'             => 15000,
        'Sprei Aja'         => 10000,
        'Sprei Single'      => 10000,
        'Karpet'            => 20000,
        'Gorden'            => 15000,
    ];

    // =========================================================================
    // FUNGSI HELPER: KIRIM WA FONNTE
    // =========================================================================
    private function sendWhatsApp($target, $message, $fileUrl = null)
    {
        // ⚠️ GANTI DENGAN TOKEN FONNTE KAMU DI SINI
        $token = 'V98JKnEB5gMHA2AnoQ3j'; 

        $postData = [
            'target' => $target,
            'message' => $message,
            'countryCode' => '62', // Agar nomor 08 otomatis jadi 628
        ];

        // Jika ada attachment gambar (seperti QR Code)
        if ($fileUrl) {
            $postData['url'] = $fileUrl;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => array(
                "Authorization: $token"
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
    // =========================================================================

    public function index()
    {
        try {
            $orders = Order::orderBy('created_at', 'desc')->get();
            return response()->json(['message' => 'Daftar semua pesanan', 'data' => $orders], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengambil data', 'error' => $e->getMessage()], 500);
        }
    }

   // Jangan lupa pastikan 2 baris ini ada di paling atas file (di bawah namespace):
    // use Illuminate\Support\Facades\Storage;
    // use SimpleSoftwareIO\QrCode\Facades\QrCode;

    public function store(Request $request)
    {
        try {
            // 1. Validasi Input
            $request->validate([
                'customer_name' => 'required',
                'wa_number'     => 'required',
                'address'       => 'required',
                'service'       => 'required',
                'weight'        => 'nullable|numeric', 
            ]);

            // 2. Persiapan Data
            $orderCode = 'LDR-' . strtoupper(Str::random(6));
            $layanan = $request->service;
            $berat = $request->weight ?? 0; 
            
            $hargaSatuan = $this->daftarHarga[$layanan] ?? 0;
            $totalHarga = $hargaSatuan * $berat;

            // 3. Simpan ke Database (Tabel Orders)
            $order = Order::create([
                'order_code'    => $orderCode,
                'customer_name' => $request->customer_name,
                'wa_number'     => $request->wa_number,
                'address'       => $request->address,
                'weight'        => $berat,
                'service'       => $layanan,
                'total_price'   => $totalHarga,
                'status'        => 'Menunggu Pembayaran',
            ]);

            // 4. Simpan Riwayat (Tabel Tracking Logs)
            TrackingLog::create([
                'order_id' => $order->id, 
                'status'   => 'Pesanan Dibuat',
                'message'  => "Pesanan {$layanan} diterima. Menunggu proses timbang.",
            ]);

            // ==========================================================
            // 5. GENERATE & SIMPAN QR CODE KE FOLDER SERVER
            // ==========================================================
           $fileName = $orderCode . '.svg';

            // Kita tembak langsung pakai disk('public') biar 100% masuk ke folder yang baru dibuat
            $qrData = QrCode::size(300)->margin(1)->generate($orderCode);
            Storage::disk('public')->put('qrcodes/' . $fileName, $qrData);

            // Membuat URL publik untuk gambar tersebut
            $qrUrl = asset('storage/qrcodes/' . $fileName); 
            // ==========================================================
            // 6. ALUR WA 1: KIRIM RESI & GAMBAR QR KE PELANGGAN
            // ==========================================================
            $pesanPelanggan = "Halo *{$request->customer_name}*! 👋\n\nTerima kasih telah mempercayakan cucian Anda di *Lyra Laundry*.\n\nPesanan Anda telah kami terima dengan detail:\n🧾 *Kode Nota:* {$orderCode}\n📦 *Layanan:* {$layanan}\n\nBerikut adalah QR Code resi Anda. Simpan gambar ini untuk mengecek status pesanan melalui aplikasi.\n\n*Lyra Laundry* 🚀";
            
            // Fonnte akan mengambil gambar dari link server kamu
            $this->sendWhatsApp($request->wa_number, $pesanPelanggan, $qrUrl);

            // ==========================================================
            // 7. ALUR WA 2: KIRIM NOTIF KE KURIR/ADMIN
            // ==========================================================
            // Mengambil nomor kurir dari .env, kalau tidak ada pakai nomor default
            $nomorKurir = env('KURIR_PHONE', '083843607098'); 
            $pesanKurir = "🚨 *TUGAS JEMPUT BARU* 🚨\n\nAda pelanggan baru masuk nih!\n\n👤 *Nama:* {$request->customer_name}\n📞 *WA:* {$request->wa_number}\n📍 *Alamat:* {$request->address}\n🧾 *Kode:* *{$orderCode}*\n\nSilakan cek detailnya di aplikasi Admin.";
            
            $this->sendWhatsApp($nomorKurir, $pesanKurir);

            // 8. Berikan Response Sukses ke Flutter
            return response()->json(['message' => 'Order Created', 'data' => $order], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateWeight(Request $request, $id)
    {
        $request->validate(['weight' => 'required|numeric']);
        $order = Order::findOrFail($id);
        
        $hargaSatuan = $this->daftarHarga[$order->service] ?? 0;
        $totalHarga = $hargaSatuan * $request->weight;

        $order->update(['weight' => $request->weight, 'total_price' => $totalHarga]);

        return response()->json(['status' => 'success', 'message' => 'Berat & Harga diupdate', 'data' => $order]);
    }

    public function updateImage(Request $request, $id)
    {
        try {
            $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg|max:10240']);
            $order = Order::findOrFail($id);
            $imagePath = $request->file('image')->store('orders', 'public');
            $order->update(['image_path' => $imagePath]);

            return response()->json(['message' => 'Foto diupload', 'image_url' => asset('storage/' . $imagePath)], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate(['status' => 'required|string']);
            $order = Order::findOrFail($id);
            $order->update(['status' => $request->status]);

            TrackingLog::create([
                'order_id' => $order->id,
                'status'   => $request->status,
                'message'  => 'Status cucian diperbarui menjadi: ' . $request->status,
            ]);

            // --- 📱 ALUR WA 3: KIRIM UPDATE TIAP KALI STATUS BERUBAH ---
            $status = $request->status;
            $pesanStatus = "Halo *{$order->customer_name}*,\n\nPemberitahuan dari *Lyra Laundry*! 📢\nStatus pesanan cucian Anda (*{$order->order_code}*) saat ini telah berubah menjadi:\n\n✨ *[ {$status} ]* ✨\n\n";

            // Kustomisasi pesan sesuai statusnya biar nggak bosenin
            if ($status == 'Menunggu Pembayaran') {
                $pesanStatus .= "Silakan lakukan pembayaran melalui aplikasi agar pesanan Anda dapat segera kami proses.";
            } elseif ($status == 'Lunas - Siap Jemput') {
                $pesanStatus .= "Pembayaran berhasil! Kurir kami akan segera meluncur ke lokasi Anda. Mohon siapkan cuciannya ya.";
            } elseif ($status == 'Proses Cuci') {
                $pesanStatus .= "Pakaian Anda sedang kami sulap jadi bersih dan wangi! Tunggu update selanjutnya ya.";
            } elseif ($status == 'Proses Antar') {
                $pesanStatus .= "Cucian Anda sudah rapi dan sedang dalam perjalanan oleh kurir kami. Siap-siap di lokasi ya!";
            } elseif ($status == 'Selesai') {
                $pesanStatus .= "Hore! Cucian Anda sudah selesai dan rapi. Terima kasih telah menggunakan jasa kami! 🙏";
            } else {
                $pesanStatus .= "Terima kasih telah mempercayakan cucian Anda kepada kami.";
            }

            $this->sendWhatsApp($order->wa_number, $pesanStatus);

            return response()->json(['message' => 'Status berhasil diupdate', 'data' => $order], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function financialReport()
    {
        $baseQuery = Order::where('status', '!=', 'Menunggu Pembayaran');

        $dailyOrders = (clone $baseQuery)->whereDate('created_at', Carbon::today())->get();
        $dailyBreakdown = $dailyOrders->map(fn($o) => ['label' => $o->customer_name . ' (' . Carbon::parse($o->created_at)->format('H:i') . ')', 'revenue' => (float) $o->total_price, 'orders' => 1]);

        $weeklyOrders = (clone $baseQuery)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get();
        $weeklyBreakdown = $weeklyOrders->groupBy(fn($o) => Carbon::parse($o->created_at)->translatedFormat('l, d M'))
            ->map(fn($group, $day) => ['label' => $day, 'revenue' => (float)$group->sum('total_price'), 'orders' => $group->count()])->values();

        $monthlyOrders = (clone $baseQuery)->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year)->get();
        $monthlyBreakdown = $monthlyOrders->groupBy(fn($o) => Carbon::parse($o->created_at)->translatedFormat('d M Y'))
            ->map(fn($group, $date) => ['label' => $date, 'revenue' => (float)$group->sum('total_price'), 'orders' => $group->count()])->values();

        return response()->json([
            'data' => [
                'daily' => ['total_revenue' => (float)$dailyOrders->sum('total_price'), 'total_orders' => $dailyOrders->count(), 'breakdown' => $dailyBreakdown],
                'weekly' => ['total_revenue' => (float)$weeklyOrders->sum('total_price'), 'total_orders' => $weeklyOrders->count(), 'breakdown' => $weeklyBreakdown],
                'monthly' => ['total_revenue' => (float)$monthlyOrders->sum('total_price'), 'total_orders' => $monthlyOrders->count(), 'breakdown' => $monthlyBreakdown],
                'yearly' => (float)(clone $baseQuery)->whereYear('created_at', Carbon::now()->year)->sum('total_price'),
                'completed_count' => Order::where('status', 'Selesai')->count(),
                'process_count' => Order::where('status', '!=', 'Selesai')->where('status', '!=', 'Menunggu Pembayaran')->count(),
            ]
        ]);
    }

    public function show($code) {
        $order = Order::with('logs')->where('order_code', $code)->first();
        return $order ? response()->json(['data' => $order], 200) : response()->json(['message' => 'Not Found'], 404);
    }

    public function updateCourierLocation(Request $request, $order_code) {
        $order = Order::where('order_code', $order_code)->first();
        if (!$order) return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan'], 404);

        $request->validate(['lat' => 'required|numeric', 'lng' => 'required|numeric']);
        $order->update(['courier_lat' => $request->lat, 'courier_lng' => $request->lng]);

        return response()->json(['success' => true, 'message' => 'Lokasi kurir diperbarui', 'data' => ['courier_lat' => $order->courier_lat, 'courier_lng' => $order->courier_lng]], 200);
    }

    public function updateLocation(Request $request, $id) {
        $order = Order::findOrFail($id);
        $order->update(['courier_lat' => $request->lat, 'courier_lng' => $request->lng, 'status' => 'Sedang Diantar']);
        return response()->json(['message' => 'Location Updated'], 200);
    }

    public function payment(Request $request, $id) {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'Sudah Dibayar']);
        TrackingLog::create(['order_id' => $order->id, 'status' => 'Pembayaran Berhasil', 'message' => 'Pesanan lunas.']);
        return response()->json(['message' => 'Payment Success'], 200);
    }
}